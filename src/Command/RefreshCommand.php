<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Exception\ApiErrorException;
use stdClass;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class RefreshCommand.
 */
class RefreshCommand extends CommandBase {

  protected static $defaultName = 'refresh';

  /**
   * @var string
   */
  protected $dir;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('refresh')
      ->setDescription('Copy code, database, and files from an Acquia Cloud environment')
      ->addArgument('dir', InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed')
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
    $this->determineDir($input);
    if ($this->dir !== '/home/ide/project' && AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      throw new AcquiaCliException('Please run this command from the {dir} directory', ['dir' => '/home/ide/project']);
    }

    $clone = $this->determineCloneProject($output);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $chosen_environment = $this->determineEnvironment($input, $output, $acquia_cloud_client);
    $checklist = new Checklist($output);
    $output_callback = static function ($type, $buffer) use ($checklist, $output) {
      if (!$output->isVerbose()) {
        $checklist->updateProgressBar($buffer);
      }
      $output->writeln($buffer, OutputInterface::VERBOSITY_VERY_VERBOSE);
    };

    if (!$input->getOption('no-code')) {
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
    if (!$input->getOption('no-databases')) {
      $database = $this->determineSourceDatabase($acquia_cloud_client, $chosen_environment);
      $checklist->addItem('Importing Drupal database copy from Acquia Cloud');
      $this->importRemoteDatabase($chosen_environment, $database, $output_callback);
      $checklist->completePreviousItem();
    }

    // Copy files.
    if (!$input->getOption('no-files')) {
      $checklist->addItem('Copying Drupal\'s public files from Acquia Cloud');
      $this->rsyncFilesFromCloud($chosen_environment, $output_callback);
      $checklist->completePreviousItem();
    }

    if (!$input->getOption('no-scripts')) {
      if (file_exists($this->dir . '/composer.json') && $this->localMachineHelper
        ->commandExists('composer')) {
        $checklist->addItem('Installing Composer dependencies');
        $this->composerInstall($output_callback);
        $checklist->completePreviousItem();
      }

      if ($this->drushHasActiveDatabaseConnection($output_callback)) {
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
  protected function drushHasActiveDatabaseConnection($output_callback = NULL): bool {
    if ($this->localMachineHelper->commandExists('drush')) {
      $process = $this->localMachineHelper->execute([
        'drush',
        'status',
        '--fields=db-status,drush-version',
        '--format=json',
        '--no-interaction',
      ], $output_callback, $this->dir, FALSE);
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
    $is_dirty = $this->isLocalGitRepoDirty();
    if ($is_dirty) {
      throw new AcquiaCliException('Pulling code from your Acquia Cloud environment was aborted because your local Git repository has uncommitted changes. Please either commit, reset, or stash your changes. Otherwise, re-run `acli refresh` with the `--no-code` option.');
    }
    $this->localMachineHelper->execute([
      'git',
      'fetch',
      '--all',
    ], $output_callback, $this->dir, FALSE);
    $this->localMachineHelper->execute([
      'git',
      'checkout',
      $chosen_environment->vcs->path,
    ], $output_callback, $this->dir, FALSE);
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
   * @return bool
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function isLocalGitRepoDirty(): bool {
    $process = $this->localMachineHelper->execute([
      'git',
      'status',
      '--short',
    ], NULL, $this->dir, FALSE);

    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Unable to determine if local git repository is dirty.");
    }

    return (bool) $process->getOutput();
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
    foreach ($application_environments as $key => $environment) {
      // Don't allow a refresh from prod.
      if ($environment->flags->production) {
        unset($application_environments[$key]);
        continue;
      }

      $choices[] = "{$environment->label} (vcs: {$environment->vcs->path})";
    }
    // Re-key the array since we removed production.
    $application_environments = array_values($application_environments);
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
    $process = $this->localMachineHelper->execute([
      'drush',
      'cache:rebuild',
      '--yes',
      '--no-interaction',
    ], $output_callback, $this->dir, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to rebuild Drupal caches via Drush. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function drushSqlSanitize($output_callback = NULL): void {
    $process = $this->localMachineHelper->execute([
      'drush',
      'sql:sanitize',
      '--yes',
      '--no-interaction',
    ], $output_callback, $this->dir, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to sanitize Drupal database via Drush. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function composerInstall($output_callback = NULL): void {
    $process = $this->localMachineHelper->execute([
      'composer',
      'install',
      '--no-interaction',
    ], $output_callback, $this->dir, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to install Drupal dependencies via Composer. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param $chosen_environment
   * @param callable $output_callback
   *
   * @throws \Exception
   */
  protected function rsyncFilesFromCloud($chosen_environment, $output_callback = NULL): void {
    $sitegroup = self::getSiteGroupFromSshUrl($chosen_environment);
    $command = [
      'rsync',
      '-rltDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      $chosen_environment->sshUrl . ':/home/' . $sitegroup . '/' . $chosen_environment->name . '/sites/default/files/',
      $this->dir . '/docroot/sites/default/',
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to sync files from Cloud. {message}', ['message' => $process->getErrorOutput()]);
    }
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
    $sitegroup = self::getSiteGroupFromSshUrl($cloud_environment);
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
    if ($this->repoRoot) {
      return FALSE;
    }
    // @todo Don't show this message when a the 'dir' argument is specified.
    $output->writeln('Could not find a local Drupal project. Looked for <options=bold>docroot/index.php</> in current and parent directories');

    if (file_exists(Path::join($this->dir, '.git'))) {
      return FALSE;
    }
    $output->writeln('Could not find a git repository in the current directory');

    $finder = $this->localMachineHelper->getFinder()->files()->in($this->dir)->ignoreDotFiles(FALSE);
    if (!$finder->hasResults()) {
      $question = new ConfirmationQuestion('<question>Would you like to clone a project into the current directory?</question> ',
        TRUE);
      if ($this->questionHelper->ask($this->input, $this->output, $question)) {
        return TRUE;
      }
    }

    $output->writeln('Could not clone into the current directory because it is not empty');

    throw new AcquiaCliException('Please execute this command from within a Drupal project directory or an empty directory');
  }

  /**
   * @param $chosen_environment
   * @param \Closure $output_callback
   *
   * @throws \Exception
   */
  protected function cloneFromCloud($chosen_environment, \Closure $output_callback): void {
    $command = [
      'GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=no"',
      'git',
      'clone',
      $chosen_environment->vcs->url,
      $this->dir,
    ];
    $command = implode(' ', $command);
    $process = $this->localMachineHelper->executeFromCmd($command, $output_callback, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Failed to clone repository from Acquia Cloud: {message}', ['message' => $process->getErrorOutput()]);
    }
    $this->repoRoot = $this->dir;
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
      $output->writeln('Using Cloud Application <options=bold>' . $cloud_application->name . '</>');
      $chosen_environment = $this->promptChooseEnvironment($acquia_cloud_client, $cloud_application_uuid);
    }
    return $chosen_environment;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   */
  protected function determineDir(InputInterface $input): void {
    if ($dir = $input->getArgument('dir')) {
      $this->dir = $dir;
    }
    elseif ($this->repoRoot) {
      $this->dir = $this->repoRoot;
    }
    else {
      $this->dir = getcwd();
    }
  }

  /**
   * @param $cloud_environment
   *
   * @return string
   */
  public static function getSiteGroupFromSshUrl($cloud_environment): string {
    $ssh_url_parts = explode('.', $cloud_environment->sshUrl);
    $sitegroup = reset($ssh_url_parts);

    return $sitegroup;
  }

}
