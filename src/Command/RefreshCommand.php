<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Environments;
use stdClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class RefreshCommand.
 */
class RefreshCommand extends CommandBase {

  protected static $defaultName = 'refresh';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('refresh')
      ->setDescription('Copy code, database, and files from an Acquia Cloud environment')
      ->addOption('cloud-env-uuid', 'from', InputOption::VALUE_REQUIRED, 'The UUID of the associated Acquia Cloud source environment')
      ->addOption('no-code', NULL, InputOption::VALUE_NONE, 'Do not refresh code from remote repository')
      ->addOption('no-files', NULL, InputOption::VALUE_NONE, 'Do not refresh files')
      ->addOption('no-databases', NULL, InputOption::VALUE_NONE, 'Do not refresh databases')
      ->addOption(
            'no-scripts',
            NULL,
            InputOption::VALUE_NONE,
            'Do not run any additional scripts after code and database are copied. E.g., composer install , drush cache-rebuild, etc.'
        )
      ->addOption('scripts', NULL, InputOption::VALUE_NONE, 'Only execute additional scripts');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $clone = $this->determineCloneProject($output);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $chosen_environment = $this->determineEnvironment($input, $output, $acquia_cloud_client);
    $checklist = new Checklist($output);
    $output_callback = static function ($type, $buffer) use ($checklist) {
      $checklist->updateProgressBar($buffer);
    };

    if ($input->getOption('no-code') !== '') {
      if ($clone) {
        $checklist->addItem('Cloning git repository from Acquia Cloud');
        $this->cloneFromCloud($chosen_environment, $output_callback);
        $checklist->completePreviousItem();
      }
      else {
        $checklist->addItem('Pulling code from Acquia Cloud');
        $this->pullCodeFromCloud($chosen_environment, $output_callback);
        $checklist->completePreviousItem();
      }
    }

    // Copy databases.
    if ($input->getOption('no-databases') !== '') {
      $database = $this->determineSourceDatabase($acquia_cloud_client, $chosen_environment);
      $checklist->addItem('Importing Drupal database copy from Acquia Cloud');
      $this->importRemoteDatabase($chosen_environment, $database, $output_callback);
      $checklist->completePreviousItem();
    }

    // Copy files.
    if ($input->getOption('no-files') !== '') {
      $checklist->addItem('Copying Drupal\'s public files from Acquia Cloud');
      $this->rsyncFilesFromCloud($chosen_environment, $output_callback);
      $checklist->completePreviousItem();
    }

    if ($input->getOption('no-scripts') !== '') {
      if (file_exists($this->repoRoot . '/composer.json') && $this->localMachineHelper
        ->commandExists('composer')) {
        $checklist->addItem('Installing Composer dependencies');
        $this->composerInstall($output_callback);
        $checklist->completePreviousItem();
      }

      if ($this->drushHasActiveDatabaseConnection()) {
        // Drush rebuild caches.
        $checklist->addItem('Clearing Drupal caches via Drush');
        $this->drushRebuildCaches($output_callback);
        $checklist->completePreviousItem();

        // Drush sanitize.
        $checklist->addItem('Sanitizing database via Drush');
        $this->drushSqlSanitize($output_callback);
        $checklist->completePreviousItem();
      }
    }

    return 0;
  }

  /**
   * @return bool
   * @throws \Exception
   */
  protected function drushHasActiveDatabaseConnection(): bool {
    if ($this->localMachineHelper->commandExists('drush')) {
      $process = $this->localMachineHelper->execute([
        'drush',
        'status',
        '--fields=db-status,drush-version',
        '--format=json',
        '--no-interaction',
      ], NULL, NULL, FALSE);
      if ($process->isSuccessful()) {
        $drush_status_return_output = json_decode($process->getOutput(), TRUE);
        if (is_array($drush_status_return_output) && array_key_exists('db-status', $drush_status_return_output) && $drush_status_return_output['db-status'] === 'Connected') {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * @param $chosen_environment
   *
   * @param callable $output_callback
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function pullCodeFromCloud($chosen_environment, $output_callback = NULL): void {
    $repo_root = $this->repoRoot;
    $is_dirty = $this->isLocalGitRepoDirty($repo_root);
    if ($is_dirty) {
      throw new AcquiaCliException('Local git is dirty!');
    }
    $this->localMachineHelper->execute([
      'git',
      'fetch',
      '--all',
    ], $output_callback, $repo_root, FALSE);
    $this->localMachineHelper->execute([
      'git',
      'checkout',
      $chosen_environment->vcs->path,
    ], $output_callback, $repo_root, FALSE);
  }

  /**
   * @param $environment
   * @param $database
   * @param string $db_host
   * @param $db_name
   * @param callable $output_callback
   *
   * @return bool
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function createAndImportRemoteDatabaseDump($environment, $database, string $db_host, $db_name, $output_callback = NULL): bool {
    $mysql_dump_filepath = $this->dumpFromRemoteHost($environment, $database, $db_host, $db_name, $output_callback);
    if (!$mysql_dump_filepath) {
      $this->output->writeln('<error>Unable to dump MySQL database on remote host.</error>');
      return FALSE;
    }

    // @todo Determine this dynamically?
    // @todo Allow to be passed by argument?
    // @todo Validate local MySQL connection before running commands.
    // @todo Enable these vars to be configured.
    $local_db_host = 'localhost';
    $local_db_user = 'drupal';
    $local_db_name = 'drupal';
    $local_db_password = 'drupal';
    // @todo See if this is successful!
    $this->dropLocalDatabase($local_db_host, $local_db_user, $local_db_name, $local_db_password, $output_callback);
    $this->createLocalDatabase($local_db_host, $local_db_user, $local_db_name, $local_db_password, $output_callback);
    $this->importDatabaseDump(
          $mysql_dump_filepath,
          $local_db_host,
          $local_db_user,
          $local_db_name,
          $local_db_password,
          $output_callback
      );

    $this->localMachineHelper->getFilesystem()->remove($mysql_dump_filepath);

    return TRUE;
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   * @param \AcquiaCloudApi\Response\DatabaseResponse $database
   * @param string $db_host
   * @param string $db_name
   *
   * @param callable $output_callback
   * @return string|null
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function dumpFromRemoteHost($environment, $database, string $db_host, $db_name, $output_callback = NULL): ?string {
    $command =  "MYSQL_PWD={$database->password} mysqldump --host={$db_host} --user={$database->user_name} {$db_name} | gzip -9";
    $process = $this->sshHelper->executeCommand($environment, [$command], FALSE);
    if ($process->isSuccessful()) {
      $filepath = $this->localMachineHelper->getFilesystem()->tempnam(sys_get_temp_dir(), $environment->uuid . '_mysqldump_');
      $filepath .= '.sql.gz';
      $this->localMachineHelper->writeFile($filepath, $process->getOutput());

      return $filepath;
    }

    return NULL;
  }

  /**
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function dropLocalDatabase($db_host, $db_user, $db_name, $db_password, $output_callback = NULL): void {
    $command = [
      'mysql',
      '--host',
      $db_host,
      '--user',
      $db_user,
      // @todo Is this insecure in any way?
      '--password=' . $db_password,
      '-e',
      'DROP DATABASE IF EXISTS ' . $db_name,
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to drop a local database. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function createLocalDatabase($db_host, $db_user, $db_name, $db_password, $output_callback = NULL): void {
    $command = [
      'mysql',
      '--host',
      $db_host,
      '--user',
      $db_user,
      // @todo Is this insecure in any way?
      '--password=' . $db_password,
      '-e',
      'create database ' . $db_name,
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to create a local database. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param string $dump_filepath
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function importDatabaseDump($dump_filepath, $db_host, $db_user, $db_name, $db_password, $output_callback = NULL): void {
    // Unfortunately we need to make this a string to prevent the '|' characters from being escaped.
    // @see https://github.com/symfony/symfony/issues/10025.
    $command = '';
    if ($this->localMachineHelper->commandExists('pv')) {
      $command .= 'pv ';
    }
    else {
      $command .= 'cat ';
    }
    $command .= "$dump_filepath | ";

    $dump_file_parts = pathinfo($dump_filepath);
    if ($dump_file_parts['extension'] === 'gz') {
      $command .= 'gunzip | ';
    }

    $command .= "MYSQL_PWD=$db_password mysql --host=$db_host --user=$db_user $db_name";

    $process = $this->localMachineHelper->executeFromCmd($command, $output_callback, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to import local database. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param string|null $repo_root
   *
   * @return bool
   * @throws \Exception
   */
  protected function isLocalGitRepoDirty(?string $repo_root): bool {
    $process = $this->localMachineHelper->execute([
      'git',
      'diff',
      '--stat',
    ], NULL, $repo_root, FALSE);

    return !$process->isSuccessful();
  }

  /**
   * @param $acquia_cloud_client
   * @param string $cloud_application_uuid
   *
   * @return mixed
   */
  protected function promptChooseEnvironment($acquia_cloud_client, $cloud_application_uuid) {
    $environment_resource = new Environments($acquia_cloud_client);
    $application_environments = iterator_to_array($environment_resource->getAll($cloud_application_uuid));
    $choices = [];
    foreach ($application_environments as $environment) {
      // Don't allow a refresh from prod.
      if (!$environment->flags->production) {
        $choices[] = "{$environment->label} (vcs: {$environment->vcs->path})";
      }
    }
    $question = new ChoiceQuestion(
          '<question>Choose an Acquia Cloud environment to copy from</question>:',
          $choices
      );
    $helper = $this->getHelper('question');
    $chosen_environment_label = $helper->ask($this->input, $this->output, $question);
    $chosen_environment_index = array_search($chosen_environment_label, $choices, TRUE);

    return $application_environments[$chosen_environment_index];
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $cloud_environment
   *
   * @param $environment_databases
   *
   * @return mixed
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function promptChooseDatabase(
    $cloud_environment,
    $environment_databases
  ) {
    $choices = [];
    $default_database_index = 0;
    if ($this->isAcsfEnv($cloud_environment)) {
      $acsf_sites = $this->getAcsfSites($cloud_environment);
    }
    foreach ($environment_databases as $index => $database) {
      $suffix = '';
      if (isset($acsf_sites)) {
        foreach ($acsf_sites['sites'] as $domain => $acsf_site) {
          if ($acsf_site['conf']['gardens_db_name'] === $database->name) {
            $suffix .= ' (' . $domain . ')';
            break;
          }
        }
      }
      if ($database->flags->default) {
        $default_database_index = $index;
        $suffix .= ' (default)';
      }
      $choices[] = $database->name . $suffix;
    }

    $question = new ChoiceQuestion(
          '<question>Choose a database to copy</question>:',
          $choices,
          $default_database_index
      );
    $helper = $this->getHelper('question');
    $chosen_database_label = $helper->ask($this->input, $this->output, $question);
    $chosen_database_index = array_search($chosen_database_label, $choices, TRUE);

    return $environment_databases[$chosen_database_index];
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $chosen_environment
   * @param object $database
   * @param callable $output_callback
   *
   * @return bool
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function importRemoteDatabase(
        $chosen_environment,
        $database,
        $output_callback = NULL
    ): bool {
    $db_url_parts = explode('/', $database->url);
    $db_name = end($db_url_parts);
    // Workaround until db_host is fixed (CXAPI-7018).
    $db_host = $database->db_host ?: "db-${$db_name}.cdb.database.services.acquia.io";
    return $this->createAndImportRemoteDatabaseDump($chosen_environment, $database, $db_host, $db_name, $output_callback);
  }

  /**
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function drushRebuildCaches($output_callback = NULL): void {
    // @todo Add support for Drush 8.
    $this->localMachineHelper->execute([
      'drush',
      'cache:rebuild',
      '--yes',
      '--no-interaction',
    ], $output_callback, $this->repoRoot, FALSE);
  }

  /**
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function drushSqlSanitize($output_callback = NULL): void {
    $this->localMachineHelper->execute([
      'drush',
      'sql:sanitize',
      '--yes',
      '--no-interaction',
    ], $output_callback, $this->repoRoot, FALSE);
  }

  /**
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function composerInstall($output_callback = NULL): void {
    $this->localMachineHelper->execute([
      'composer',
      'install',
      '--no-interaction',
    ], $output_callback, $this->repoRoot, FALSE);
  }

  /**
   * @param $chosen_environment
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function rsyncFilesFromCloud($chosen_environment, $output_callback = NULL): void {
    $command = [
      'rsync',
      '-rve',
      'ssh -o StrictHostKeyChecking=no',
      $chosen_environment->sshUrl . ':/' . $chosen_environment->name . '/sites/default/files',
      $this->repoRoot . '/docroot/sites/default',
    ];
    $this->localMachineHelper->execute($command, $output_callback, NULL, FALSE);
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param $chosen_environment
   *
   * @return object
   * @throws \Exception
   */
  protected function determineSourceDatabase(Client $acquia_cloud_client, $chosen_environment): stdClass {
    $databases = $acquia_cloud_client->request(
          'get',
          '/environments/' . $chosen_environment->uuid . '/databases'
      );
    if (count($databases) > 1) {
      $database = $this->promptChooseDatabase($chosen_environment, $databases);
    }
    else {
      $database = reset($databases);
    }

    return $database;
  }

  /**
   * @param $cloud_environment
   *
   * @return bool
   */
  protected function isAcsfEnv($cloud_environment): bool {
    if (strpos($cloud_environment->sshUrl, 'enterprise-g1') !== FALSE) {
      return TRUE;
    }
    foreach ($cloud_environment->domains as $domain) {
      if (strpos($domain, 'acsitefactory') !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $cloud_environment
   *
   * @return array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getAcsfSites($cloud_environment): array {
    $ssh_url_parts = explode('.', $cloud_environment->sshUrl);
    $sitegroup = reset($ssh_url_parts);
    $command = ['cat', "/var/www/site-php/$sitegroup.{$cloud_environment->name}/multisite-config.json"];
    $process = $this->sshHelper->executeCommand($cloud_environment, $command);
    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE);
    }
    throw new AcquiaCliException("Could not get ACSF sites");
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return bool
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determineCloneProject(OutputInterface $output): bool {
    $clone = FALSE;
    if (!$this->repoRoot) {
      $output->writeln('Could not find a local Drupal project. Looked for <comment>docroot/index.php</comment> in current and parent directories.');
      $question = new ConfirmationQuestion('<question>Would you like to clone a project into the current directory?</question>',
        TRUE);
      $answer = $this->questionHelper->ask($this->input, $this->output, $question);
      if ($answer) {
        $clone = TRUE;
      }
      else {
        throw new AcquiaCliException('Please execute this command from within a Drupal project directory');
      }
    }
    return $clone;
  }

  /**
   * @param $chosen_environment
   * @param \Closure $output_callback
   *
   * @throws \Exception
   */
  protected function cloneFromCloud($chosen_environment, \Closure $output_callback): void {
    $command = [
      'git',
      'clone',
      $chosen_environment->vcs->url,
      '.',
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Failed to clone repository from Acquia Cloud: {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $acquia_cloud_client
   *
   * @return \AcquiaCloudApi\Response\EnvironmentResponse|mixed
   * @throws \Exception
   */
  protected function determineEnvironment(InputInterface $input, OutputInterface $output, $acquia_cloud_client) {
    if ($input->getOption('cloud-env-uuid')) {
      $environment_id = $input->getOption('cloud-env-uuid');
      $chosen_environment = $this->getCloudEnvironment($environment_id);
      // @todo Write "Using Cloud Application ...".
    }
    else {
      $cloud_application_uuid = $this->determineCloudApplication();
      $cloud_application = $this->getCloudApplication($cloud_application_uuid);
      $output->writeln('Using Cloud Application <comment>' . $cloud_application->name . '</comment>');
      $chosen_environment = $this->promptChooseEnvironment($acquia_cloud_client, $cloud_application_uuid);
    }
    return $chosen_environment;
  }

}
