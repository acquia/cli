<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\ClientInterface;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class RefreshCommand.
 */
class RefreshCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('refresh')
      ->setDescription('Copy code, database, and files from an Acquia Cloud environment')
      ->addOption('from', NULL, InputOption::VALUE_NONE, 'The source environment')
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
    // @todo Add option to allow specifying source environment uuid.
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // @todo Identify a valid target, throw exception if not found.
    $this->validateCwdIsValidDrupalProject();

    // Choose remote environment.
    $cloud_application_uuid = $this->determineCloudApplication();
    // @todo Write name of Cloud application to screen.
    $acquia_cloud_client = $this->getAcquiaCloudClient();
    $chosen_environment = $this->promptChooseEnvironment($acquia_cloud_client, $cloud_application_uuid);
    $checklist = new Checklist($output);
    $output_callback = static function ($type, $buffer) use ($checklist) {
      $checklist->updateProgressBar($buffer);
    };

    // Copy databases.
    if (!$input->getOption('no-databases')) {
      $database = $this->determineSourceDatabase($acquia_cloud_client, $chosen_environment);
      $checklist->addItem('Importing Drupal database copy from Acquia Cloud');
      $this->importRemoteDatabase($chosen_environment, $database, $output_callback);
      $checklist->completePreviousItem();
    }

    // Git clone if no local repo found.
    // @todo This won't actually execute if repo is missing because of $this->validateCwdIsValidDrupalProject();
    // This is a bug!
    if (!$input->getOption('no-code')) {
      $checklist->addItem('Pulling code from Acquia Cloud');
      $this->pullCodeFromCloud($chosen_environment, $output_callback);
      $checklist->completePreviousItem();
    }

    // Copy files.
    if (!$input->getOption('no-files')) {
      $checklist->addItem('Copying Drupal\'s public files from Acquia Cloud');
      $this->rsyncFilesFromCloud($chosen_environment, $output_callback);
      $checklist->completePreviousItem();
    }

    if (!$input->getOption('no-scripts')) {
      if (file_exists($this->getApplication()->getRepoRoot() . '/composer.json') && $this->getApplication()
        ->getLocalMachineHelper()
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
   */
  protected function drushHasActiveDatabaseConnection(): bool {
    if ($this->getApplication()->getLocalMachineHelper()->commandExists('drush')) {
      $process = $this->getApplication()->getLocalMachineHelper()->execute([
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function pullCodeFromCloud($chosen_environment, $output_callback = NULL): void {
    $repo_root = $this->getApplication()->getRepoRoot();
    if (!file_exists($repo_root . '/.git')) {
      $this->getApplication()->getLocalMachineHelper()->execute([
        'git',
        'clone',
        $chosen_environment->vcs->url,
        $this->getApplication()->getRepoRoot(),
      ], $output_callback);
    }
    else {
      $is_dirty = $this->isLocalGitRepoDirty($repo_root);
      if ($is_dirty) {
        throw new AcquiaCliException('Local git is dirty!');
      }
      $this->getApplication()->getLocalMachineHelper()->execute([
        'git',
        'fetch',
        '--all',
      ], $output_callback, $repo_root, FALSE);
      $this->getApplication()->getLocalMachineHelper()->execute([
        'git',
        'checkout',
        $chosen_environment->vcs->path,
      ], $output_callback, $repo_root, FALSE);
    }
  }

  /**
   * @param $environment
   * @param $database
   * @param string $db_host
   * @param $db_name
   * @param callable $output_callback
   */
  protected function createAndImportRemoteDatabaseDump($environment, $database, string $db_host, $db_name, $output_callback = NULL): void {
    $mysql_dump_filepath = $this->dumpFromRemoteHost($environment, $database, $db_host, $db_name, $output_callback);

    // @todo Determine this dynamically?
    // @todo Validate local MySQL connection before running commands.
    // @todo Enable these vars to be configured.
    $local_db_host = 'localhost';
    $local_db_user = 'drupal';
    $local_db_name = 'drupal';
    $local_db_password = 'drupal';
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
  }

  /**
   * @param $environment
   * @param $database
   * @param string $db_host
   * @param $db_name
   *
   * @return string|null
   */
  protected function dumpFromRemoteHost($environment, $database, string $db_host, $db_name, $output_callback = NULL): ?string {
    $process = $this->getApplication()->getLocalMachineHelper()->execute([
      'ssh',
      '-T',
      '-o',
      'StrictHostKeyChecking no',
      '-o',
      'LogLevel=ERROR',
      $environment->sshUrl,
      "MYSQL_PWD={$database->password} mysqldump --host={$db_host} --user={$database->user_name} {$db_name} | gzip -9",
    ], $output_callback, NULL, FALSE);

    if ($process->isSuccessful()) {
      $fs = $this->getApplication()->getLocalMachineHelper()->getFilesystem();
      $filepath = $fs->tempnam(sys_get_temp_dir(), $environment->uuid . '_mysqldump_');
      $filepath .= '.sql.gz';
      $fs->dumpFile($filepath, $process->getOutput());

      return $filepath;
    }

    return NULL;
  }

  /**
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   */
  protected function dropLocalDatabase($db_host, $db_user, $db_name, $db_password, $output_callback = NULL): void {
    $this->getApplication()->getLocalMachineHelper()->execute([
      'mysql',
      '--host',
      $db_host,
      '--user',
      $db_user,
          // @todo Is this insecure in any way?
      '--password=' . $db_password,
      '-e',
      'DROP DATABASE IF EXISTS ' . $db_name,
    ], $output_callback, NULL, FALSE);
  }

  /**
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   */
  protected function createLocalDatabase($db_host, $db_user, $db_name, $db_password, $output_callback = NULL): void {
    $this->getApplication()->getLocalMachineHelper()->execute([
      'mysql',
      '--host',
      $db_host,
      '--user',
      $db_user,
          // @todo Is this insecure in any way?
      '--password=' . $db_password,
      '-e',
      'create database ' . $db_name,
    ], $output_callback, NULL, FALSE);
  }

  /**
   * @param string $dump_filepath
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   */
  protected function importDatabaseDump($dump_filepath, $db_host, $db_user, $db_name, $db_password, $output_callback = NULL): void {
    // Unfortunately we need to make this a string to prevent the '|' characters from being escaped.
    // @see https://github.com/symfony/symfony/issues/10025.
    $command = '';
    if ($this->getApplication()->getLocalMachineHelper()->commandExists('pv')) {
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

    $this->getApplication()->getLocalMachineHelper()->executeFromCmd($command, $output_callback, NULL, FALSE);
  }

  /**
   * @param string|null $repo_root
   *
   * @return bool
   */
  protected function isLocalGitRepoDirty(?string $repo_root): bool {
    $process = $this->getApplication()->getLocalMachineHelper()->execute([
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
   * @param $acquia_cloud_client
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $cloud_environment
   *
   * @return mixed
   * @throws \Exception
   */
  protected function promptChooseDatabase(ClientInterface $acquia_cloud_client, $cloud_environment) {
    $response = $acquia_cloud_client->makeRequest('get', '/environments/' . $cloud_environment->uuid . '/databases');
    $environment_databases = $acquia_cloud_client->processResponse($response);

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
   */
  protected function importRemoteDatabase(
        $chosen_environment,
        $database,
        $output_callback = NULL
    ): void {
    $db_url_parts = explode('/', $database->url);
    $db_name = end($db_url_parts);
    // Workaround until db_host is fixed (CXAPI-7018).
    $db_host = $database->db_host ?: "db-${$db_name}.cdb.database.services.acquia.io";
    $this->createAndImportRemoteDatabaseDump($chosen_environment, $database, $db_host, $db_name, $output_callback);
  }

  /**
   *
   */
  protected function drushRebuildCaches($output_callback = NULL): void {
    // @todo Add support for Drush 8.
    $this->getApplication()->getLocalMachineHelper()->execute([
      'drush',
      'cache:rebuild',
      '--yes',
      '--no-interaction',
    ], $output_callback, $this->getApplication()->getRepoRoot(), FALSE);
  }

  /**
   *
   */
  protected function drushSqlSanitize($output_callback = NULL): void {
    $this->getApplication()->getLocalMachineHelper()->execute([
      'drush',
      'sql:sanitize',
      '--yes',
      '--no-interaction',
    ], $output_callback, $this->getApplication()->getRepoRoot(), FALSE);
  }

  /**
   *
   */
  protected function composerInstall($output_callback = NULL): void {
    $this->getApplication()->getLocalMachineHelper()->execute([
      'composer',
      'install',
      '--no-interaction',
    ], $output_callback, $this->getApplication()->getRepoRoot(), FALSE);
  }

  /**
   * @param $chosen_environment
   */
  protected function rsyncFilesFromCloud($chosen_environment, $output_callback = NULL): void {
    $this->getApplication()->getLocalMachineHelper()->execute([
      'rsync',
      '-rve',
      'ssh -o StrictHostKeyChecking=no',
      $chosen_environment->sshUrl . ':/' . $chosen_environment->name . '/sites/default/files',
      $this->getApplication()->getRepoRoot() . '/docroot/sites/default',
    ], $output_callback, NULL, FALSE);
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param $chosen_environment
   *
   * @return object
   * @throws \Exception
   */
  protected function determineSourceDatabase(Client $acquia_cloud_client, $chosen_environment): \stdClass {
    $response = $acquia_cloud_client->makeRequest(
          'get',
          '/environments/' . $chosen_environment->uuid . '/databases'
      );
    $databases = $acquia_cloud_client->processResponse($response);
    if (count($databases) > 1) {
      $database = $this->promptChooseDatabase($acquia_cloud_client, $chosen_environment);
    }
    else {
      $database = reset($databases);
    }

    return $database;
  }

  /**
   * @param $cloud_environment
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
   * @param $cloud_environment
   *
   * @return array
   */
  protected function getAcsfSites($cloud_environment): array {
    $ssh_url_parts = explode('.', $cloud_environment->sshUrl);
    $sitegroup = reset($ssh_url_parts);
    $process = $this->getApplication()->getLocalMachineHelper()->execute([
      'ssh',
      '-T',
      '-o',
      'StrictHostKeyChecking no',
      '-o',
      'LogLevel=ERROR',
      $cloud_environment->sshUrl,
      "cat /var/www/site-php/$sitegroup.{$cloud_environment->name}/multisite-config.json",
    ], NULL, NULL, FALSE);
    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE);
    }

    return NULL;
  }

}
