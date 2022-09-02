<?php

namespace Acquia\Cli\Command\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\IdeCommandTrait;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\DatabaseBackups;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\BackupResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Closure;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\UriInterface;
use React\EventLoop\Loop;
use stdClass;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Filesystem\Path;

/**
 * Class PullCommandBase.
 */
abstract class PullCommandBase extends CommandBase {

  use IdeCommandTrait;

  /**
   * @var Checklist
   */
  protected $checklist;

  /**
   * @var EnvironmentResponse
   */
  protected $sourceEnvironment;

  /**
   * @var string
   */
  private $site;

  private UriInterface $backupDownloadUrl;

  /**
   * @param $environment
   * @param $database
   * @param $backup_response
   *
   * @return string
   */
  public static function getBackupPath($environment, $database, $backup_response): string {
    // Filename roughly matches what you'd get with a manual download from Cloud UI.
    $filename = implode('-', [
        $environment->name,
        $database->name,
        trim(parse_url($database->url, PHP_URL_PATH), '/'),
        $backup_response->completedAt
      ]) . '.sql.gz';
    return Path::join(sys_get_temp_dir(), $filename);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->checklist = new Checklist($output);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    return 0;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function pullCode(InputInterface $input, OutputInterface $output): void {
    $this->setDirAndRequireProjectCwd($input);
    $clone = $this->determineCloneProject($output);
    $source_environment = $this->determineEnvironment($input, $output, TRUE);

    if ($clone) {
      $this->checklist->addItem('Cloning git repository from the Cloud Platform');
      $this->cloneFromCloud($source_environment, $this->getOutputCallback($output, $this->checklist));
    }
    else {
      $this->checklist->addItem('Pulling code from the Cloud Platform');
      $this->pullCodeFromCloud($source_environment, $this->getOutputCallback($output, $this->checklist));
    }
    $this->checklist->completePreviousItem();
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param bool $on_demand Force on-demand backup.
   * @param bool $no_import Skip import.
   * @param bool $multiple_dbs
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function pullDatabase(InputInterface $input, OutputInterface $output, bool $on_demand = FALSE, bool $no_import = FALSE, bool $multiple_dbs = FALSE): void {
    if (!$no_import) {
      // Verify database connection.
      $this->connectToLocalDatabase($this->getDefaultLocalDbHost(), $this->getDefaultLocalDbUser(), $this->getDefaultLocalDbName(), $this->getDefaultLocalDbPassword(), $this->getOutputCallback($output, $this->checklist));
    }
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $source_environment = $this->determineEnvironment($input, $output, TRUE);
    $site = $this->determineSite($source_environment, $input);
    $databases = $this->determineCloudDatabases($acquia_cloud_client, $source_environment, $site, $multiple_dbs);

    foreach ($databases as $database) {
      if ($on_demand) {
        $this->checklist->addItem("Creating an on-demand database(s) backup on Cloud Platform");
        $this->createBackup($source_environment, $database, $acquia_cloud_client);
        $this->checklist->completePreviousItem();
      }
      $backup_response = $this->getDatabaseBackup($acquia_cloud_client, $source_environment, $database);
      if (!$on_demand) {
        $this->printDatabaseBackupInfo($backup_response, $source_environment);
      }

      $this->checklist->addItem("Downloading {$database->name} database copy from the Cloud Platform");
      $local_filepath = $this->downloadDatabaseBackup($source_environment, $database, $backup_response, $this->getOutputCallback($output, $this->checklist));
      $this->checklist->completePreviousItem();

      if ($no_import) {
        $this->io->success("{$database->name} database backup downloaded to $local_filepath");
      }
      else {
        $this->checklist->addItem("Importing {$database->name} database download");
        $this->importRemoteDatabase($database, $local_filepath, $this->getOutputCallback($output, $this->checklist));
        $this->checklist->completePreviousItem();
      }
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Exception
   */
  protected function pullFiles(InputInterface $input, OutputInterface $output): void {
    $this->setDirAndRequireProjectCwd($input);
    $source_environment = $this->determineEnvironment($input, $output, TRUE);
    $this->checklist->addItem('Copying Drupal\'s public files from the Cloud Platform');
    $site = $this->determineSite($source_environment, $input);
    $this->rsyncFilesFromCloud($source_environment, $this->getOutputCallback($output, $this->checklist), $site);
    $this->checklist->completePreviousItem();
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $chosen_environment
   *
   * @param null $output_callback
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function pullCodeFromCloud(EnvironmentResponse $chosen_environment, $output_callback = NULL): void {
    $is_dirty = $this->isLocalGitRepoDirty();
    if ($is_dirty) {
      throw new AcquiaCliException('Pulling code from your Cloud Platform environment was aborted because your local Git repository has uncommitted changes. Please either commit, reset, or stash your changes via git.');
    }
    // @todo Validate that an Acquia remote is configured for this repository.
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $this->localMachineHelper->execute([
      'git',
      'fetch',
      '--all',
    ], $output_callback, $this->dir, FALSE);
    $this->checkoutBranchFromEnv($chosen_environment, $output_callback);
  }

  /**
   * Checks out the matching branch from a source environment.
   *
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   * @param null $output_callback
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function checkoutBranchFromEnv(EnvironmentResponse $environment, $output_callback = NULL): void {
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $this->localMachineHelper->execute([
      'git',
      'checkout',
      $environment->vcs->path,
    ], $output_callback, $this->dir, FALSE);
  }

  /**
   * @param string $database_host
   * @param string $database_user
   * @param string $database_name
   * @param string $database_password
   * @param string $local_filepath
   * @param null $output_callback
   *
   * @throws \Exception
   */
  private function doImportRemoteDatabase(
    string $database_host,
    string $database_user,
    string $database_name,
    string $database_password,
    string $local_filepath,
           $output_callback = NULL
  ): void {
    $this->dropLocalDatabase($database_host, $database_user, $database_name, $database_password, $output_callback);
    $this->createLocalDatabase($database_host, $database_user, $database_name, $database_password, $output_callback);
    $this->importDatabaseDump($local_filepath, $database_host, $database_user, $database_name, $database_password, $output_callback);
    $this->localMachineHelper->getFilesystem()->remove($local_filepath);
  }

  /**
   * @param EnvironmentResponse $environment
   * @param $database
   * @param \AcquiaCloudApi\Response\BackupResponse $backup_response
   * @param callable|null $output_callback
   *
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function downloadDatabaseBackup(
    $environment,
    $database,
    BackupResponse $backup_response,
    $output_callback = NULL
  ): string {
    if ($output_callback) {
      $output_callback('out', "Downloading backup {$backup_response->id}");
    }
    $local_filepath = self::getBackupPath($environment, $database, $backup_response);
    if ($this->output instanceof ConsoleOutput) {
      $output = $this->output->section();
    }
    else {
      $output = $this->output;
    }
    // These options tell curl to stream the file to disk rather than loading it into memory.
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $acquia_cloud_client->addOption('sink', $local_filepath);
    $acquia_cloud_client->addOption('curl.options', ['CURLOPT_RETURNTRANSFER' => FALSE, 'CURLOPT_FILE' => $local_filepath]);
    $acquia_cloud_client->addOption('progress', static function ($total_bytes, $downloaded_bytes, $upload_total, $uploaded_bytes) use (&$progress, $output) {
      self::displayDownloadProgress($total_bytes, $downloaded_bytes, $progress, $output);
    });
    // This is really just used to allow us to inject values for $url during testing.
    // It should be empty during normal operations.
    $url = $this->getBackupDownloadUrl();
    $acquia_cloud_client->addOption('on_stats', function (TransferStats $stats) use (&$url) {
      $url = $stats->getEffectiveUri();
    });

    try {
      $response = $acquia_cloud_client->stream("get", "/environments/{$environment->uuid}/databases/{$database->name}/backups/{$backup_response->id}/actions/download", $acquia_cloud_client->getOptions());
      return $local_filepath;
    }
    catch (RequestException $exception) {
      // @see https://timi.eu/docs/anatella/5_1_9_1_list-of-curl-error-co.html
      if (in_array($exception->getHandlerContext()['errno'], [51, 60])) {
        $domains_resource = new Domains($acquia_cloud_client);
        $domains = $domains_resource->getAll($environment->uuid);
        foreach ($domains as $domain) {
          if ($domain->hostname === $url->getHost()) {
            continue;
          }
          $output_callback('out', '<comment>The certificate for ' . $url->getHost() . ' is invalid, trying alternative host ' . $domain->hostname . ' </comment>');
          $download_url = $url->withHost($domain->hostname);
          try {
            $response = $acquia_cloud_client->stream("get", $download_url, $acquia_cloud_client->getOptions());
            return $local_filepath;
          }
          catch (\Exception $exception) {
            // Continue in the foreach() loop.
          }
        }
      }
    }

    // If we looped through all domains and got here, we didn't download anything.
    throw new AcquiaCliException('Could not download backup');
  }

  /**
   * @param \Psr\Http\Message\UriInterface $url
   */
  public function setBackupDownloadUrl(UriInterface $url) {
    $this->backupDownloadUrl = $url;
  }

  /**
   * @return \Psr\Http\Message\UriInterface|null
   */
  private function getBackupDownloadUrl(): ?UriInterface {
    if (isset($this->backupDownloadUrl)) {
      return $this->backupDownloadUrl;
    }
    return NULL;
  }

  /**
   * @param $total_bytes
   * @param $downloaded_bytes
   * @param $progress
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public static function displayDownloadProgress($total_bytes, $downloaded_bytes, &$progress, OutputInterface $output): void {
    if ($total_bytes > 0 && is_null($progress)) {
      $progress = new ProgressBar($output, $total_bytes);
      $progress->setFormat('        %current%/%max% [%bar%] %percent:3s%%');
      $progress->setProgressCharacter('💧');
      $progress->setOverwrite(TRUE);
      $progress->start();
    }

    if (!is_null($progress)) {
      if ($total_bytes === $downloaded_bytes && $progress->getProgressPercent() !== 1.0) {
        $progress->finish();
        if ($output instanceof ConsoleSectionOutput) {
          $output->clear();
        }
        return;
      }
      $progress->setProgress($downloaded_bytes);
    }
  }

  /**
   * Create an on-demand backup and wait for it to become available.
   *
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   * @param \stdClass $database
   * @param $acquia_cloud_client
   */
  private function createBackup(EnvironmentResponse $environment, stdClass $database, $acquia_cloud_client): void {
    $backups = new DatabaseBackups($acquia_cloud_client);
    $response = $backups->create($environment->uuid, $database->name);
    $url_parts = explode('/', $response->links->notification->href);
    $notification_uuid = end($url_parts);
    $this->waitForBackup($notification_uuid, $acquia_cloud_client);
  }

  /**
   * Wait for an on-demand backup to become available (Cloud API notification).
   *
   * @param string $notification_uuid
   * @param $acquia_cloud_client
   *
   * @infection-ignore-all
   */
  protected function waitForBackup(string $notification_uuid, $acquia_cloud_client): void {
    $spinnerMessage = 'Waiting for database backup to complete...';
    $successCallback = function () {
      $this->output->writeln('');
      $this->output->writeln('<info>Database backup is ready!</info>');
    };
    $this->waitForNotificationToComplete($acquia_cloud_client, $notification_uuid, $spinnerMessage, $successCallback);
    Loop::run();
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
  private function connectToLocalDatabase($db_host, $db_user, $db_name, $db_password, $output_callback = NULL): void {
    if ($output_callback) {
      $output_callback('out', "Connecting to database $db_name");
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['mysql']);
    $command = [
      'mysql',
      '--host',
      $db_host,
      '--user',
      $db_user,
      $db_name
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, FALSE, NULL, ['MYSQL_PWD' => $db_password]);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to connect to local database using credentials mysql:://{user}:{password}@{host}/{database}. {message}', [
        'message' => $process->getErrorOutput(),
        'host' => $db_host,
        'user' => $db_user,
        'password' => $db_password,
        'database' => $db_name,
      ]);
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
  private function dropLocalDatabase($db_host, $db_user, $db_name, $db_password, $output_callback = NULL): void {
    if ($output_callback) {
      $output_callback('out', "Dropping database $db_name");
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['mysql']);
    $command = [
      'mysql',
      '--host',
      $db_host,
      '--user',
      $db_user,
      '-e',
      'DROP DATABASE IF EXISTS ' . $db_name,
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, FALSE, NULL, ['MYSQL_PWD' => $db_password]);
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
  private function createLocalDatabase($db_host, $db_user, $db_name, $db_password, $output_callback = NULL): void {
    if ($output_callback) {
      $output_callback('out', "Creating new empty database $db_name");
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['mysql']);
    $command = [
      'mysql',
      '--host',
      $db_host,
      '--user',
      $db_user,
      '-e',
      'create database ' . $db_name,
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, FALSE, NULL, ['MYSQL_PWD' => $db_password]);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to create a local database. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param string $local_dump_filepath
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   * @param null $output_callback
   *
   * @throws \Exception
   */
  private function importDatabaseDump(string $local_dump_filepath, string $db_host, string $db_user, string $db_name, string $db_password, $output_callback = NULL): void {
    if ($output_callback) {
      $output_callback('out', "Importing downloaded file to database $db_name");
    }
    $this->logger->debug("Importing $local_dump_filepath to MySQL on local machine");
    $this->localMachineHelper->checkRequiredBinariesExist(['gunzip', 'mysql']);
    if ($this->localMachineHelper->commandExists('pv')) {
      $command = "pv $local_dump_filepath --bytes --rate | gunzip | MYSQL_PWD=$db_password mysql --host=$db_host --user=$db_user $db_name";
    }
    else {
      $this->io->warning('Please install `pv` to see progress bar');
      $command = "gunzip $local_dump_filepath | MYSQL_PWD=$db_password mysql --host=$db_host --user=$db_user $db_name";
    }

    $process = $this->localMachineHelper->executeFromCmd($command, $output_callback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL), NULL);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to import local database. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @return bool
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function isLocalGitRepoDirty(): bool {
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $process = $this->localMachineHelper->executeFromCmd(
      // Problem with this is that it stages changes for the user. They may
      // not want that.
      'git add . && git diff-index --cached --quiet HEAD',
     NULL, $this->dir, FALSE);

    return !$process->isSuccessful();
  }

  protected function getLocalGitCommitHash(): string {
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $process = $this->localMachineHelper->execute([
      'git',
      'rev-parse',
      'HEAD',
    ], NULL, $this->dir, FALSE);

    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Unable to determine Git commit hash.");
    }

    return trim($process->getOutput());
  }

  /**
   * @param $acquia_cloud_client
   * @param string $cloud_application_uuid
   * @param bool $allow_production
   *
   * @return array|string
   */
  private function promptChooseEnvironment($acquia_cloud_client, $cloud_application_uuid, $allow_production = FALSE) {
    $environment_resource = new Environments($acquia_cloud_client);
    $application_environments = iterator_to_array($environment_resource->getAll($cloud_application_uuid));
    $choices = [];
    foreach ($application_environments as $key => $environment) {
      if (!$allow_production && $environment->flags->production) {
        unset($application_environments[$key]);
        // Re-index array so keys match those in $choices.
        $application_environments = array_values($application_environments);
        continue;
      }
      $choices[] = "{$environment->label}, {$environment->name} (vcs: {$environment->vcs->path})";
    }
    $chosen_environment_label = $this->io->choice('Choose a Cloud Platform environment', $choices, $choices[0]);
    $chosen_environment_index = array_search($chosen_environment_label, $choices, TRUE);

    return $application_environments[$chosen_environment_index];
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $cloud_environment
   *
   * @param $environment_databases
   *
   * @return array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function promptChooseDatabases(
    $cloud_environment,
    $environment_databases,
    $multiple_dbs
  ) {
    $choices = [];
    if ($multiple_dbs) {
      $choices['all'] = 'All';
    }
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
      $multiple_dbs ? 'Choose databases. You may choose multiple. Use commas to separate choices.' : 'Choose a database.',
      $choices,
      $default_database_index
    );
    $question->setMultiselect($multiple_dbs);
    if ($multiple_dbs) {
      $chosen_database_keys = $this->io->askQuestion($question);
      $chosen_databases = [];
      if (count($chosen_database_keys) === 1 && $chosen_database_keys[0] === 'all') {
        if (count($environment_databases) > 10) {
          $this->io->warning('You have chosen to pull down more than 10 databases. This could exhaust your disk space.');
        }
        return $environment_databases;
      }
      foreach ($chosen_database_keys as $chosen_database_key) {
        $chosen_databases[] = $environment_databases[$chosen_database_key];
      }

      return $chosen_databases;
    }
    else {
      $chosen_database_label = $this->io->choice('Choose a database', $choices, $default_database_index);
      $chosen_database_index = array_search($chosen_database_label, $choices, TRUE);
      return [$environment_databases[$chosen_database_index]];
    }
  }

  /**
   * @param callable|null $output_callback
   *
   * @throws \Exception
   */
  protected function runComposerScripts(callable $output_callback = NULL): void {
    if (file_exists($this->dir . '/composer.json') && $this->localMachineHelper->commandExists('composer')) {
      $this->checklist->addItem("Installing Composer dependencies");
      $this->composerInstall($output_callback);
      $this->checklist->completePreviousItem();
    }
    else {
      $this->logger->notice('composer or composer.json file not found. Skipping composer install.');
    }
  }

  /**
   * @param $environment
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return mixed|string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function determineSite($environment, InputInterface $input) {
    if (isset($this->site)) {
      return $this->site;
    }

    if ($input->hasArgument('site') && $input->getArgument('site')) {
      return $input->getArgument('site');
    }
    elseif ($this->isAcsfEnv($environment)) {
      $site = $this->promptChooseAcsfSite($environment);
    }
    else {
      $site = $this->promptChooseCloudSite($environment);
    }
    $this->site = $site;

    return $site;
  }

  /**
   * @param $chosen_environment
   * @param \Closure|null $output_callback
   * @param string|null $site
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function rsyncFilesFromCloud($chosen_environment, Closure $output_callback = NULL, string $site): void {
    $sitegroup = self::getSiteGroupFromSshUrl($chosen_environment->sshUrl);
    if ($this->isAcsfEnv($chosen_environment)) {
      $source_dir = '/mnt/files/' . $sitegroup . '.' . $chosen_environment->name . '/sites/g/files/' . $site . '/files';
      $destination = $this->dir . '/docroot/sites/' . $site . '/';
    }
    else {
      $source_dir = $this->getCloudSitesPath($chosen_environment, $sitegroup) . "/$site/files/";
      $destination = $this->dir . '/docroot/sites/' . $site . '/files';
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['rsync']);
    $this->localMachineHelper->getFilesystem()->mkdir($destination);
    $command = [
      'rsync',
      // -a archive mode; same as -rlptgoD.
      // -z compress file data during the transfer.
      // -v increase verbosity.
      // -P show progress during transfer.
      // -h output numbers in a human-readable format.
      // -e specify the remote shell to use.
      '-avPhze',
      'ssh -o StrictHostKeyChecking=no',
      $chosen_environment->sshUrl . ':' . $source_dir,
      $destination,
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, FALSE, 60 * 60);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to sync files from Cloud. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param EnvironmentResponse $chosen_environment
   * @param string|null $site
   * @param bool $multiple_dbs
   *
   * @return \stdClass[]
   *   The database instance. This is not a DatabaseResponse, since it's
   *   specific to the environment.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determineCloudDatabases(Client $acquia_cloud_client, $chosen_environment, $site = NULL, $multiple_dbs = FALSE) {
    $databases = $acquia_cloud_client->request(
      'get',
      '/environments/' . $chosen_environment->uuid . '/databases'
    );

    if (count($databases) > 1) {
      $this->logger->debug('Multiple databases detected on Cloud');
      if ($site && !$multiple_dbs) {
        if ($site === 'default') {
          $this->logger->debug('Site is set to default. Assuming default database');
          $site = self::getSiteGroupFromSshUrl($chosen_environment->sshUrl);
        }
        $database_names = array_column($databases, 'name');
        $database_key = array_search($site, $database_names);
        if ($database_key !== FALSE) {
          return [$databases[$database_key]];
        }
      }
      return $this->promptChooseDatabases($chosen_environment, $databases, $multiple_dbs);
    }
    else {
      $this->logger->debug('Only a single database detected on Cloud');
    }

    return [reset($databases)];
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return bool
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function determineCloneProject(OutputInterface $output): bool {
    $finder = $this->localMachineHelper->getFinder()->files()->in($this->dir)->ignoreDotFiles(FALSE);

    // If we are in an IDE, assume we should pull into /home/ide/project.
    if ($this->dir === '/home/ide/project' && AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !$finder->hasResults()) {
      $output->writeln('Cloning into current directory.');
      return TRUE;
    }

    // If $this->repoRoot is set, pull into that dir rather than cloning.
    if ($this->repoRoot) {
      return FALSE;
    }

    // If ./.git exists, assume we pull into that dir rather than cloning.
    if (file_exists(Path::join($this->dir, '.git'))) {
      return FALSE;
    }
    $output->writeln('Could not find a git repository in the current directory');

    if (!$finder->hasResults() && $this->io->confirm('Would you like to clone a project into the current directory?')) {
      return TRUE;
    }

    $output->writeln('Could not clone into the current directory because it is not empty');

    throw new AcquiaCliException('Please execute this command from within a Drupal project directory or an empty directory');
  }

  /**
* @param \AcquiaCloudApi\Response\EnvironmentResponse $chosen_environment
   * @param \Closure $output_callback
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function cloneFromCloud(EnvironmentResponse $chosen_environment, Closure $output_callback): void {
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $command = [
      'git',
      'clone',
      $chosen_environment->vcs->url,
      $this->dir,
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL), NULL, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=no']);
    $this->checkoutBranchFromEnv($chosen_environment, $output_callback);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Failed to clone repository from the Cloud Platform: {message}', ['message' => $process->getErrorOutput()]);
    }
    $this->repoRoot = $this->dir;
  }

  /**
* @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param bool $allow_production
   * @return \AcquiaCloudApi\Response\EnvironmentResponse|mixed
   * @throws \Exception
   */
  protected function determineEnvironment(InputInterface $input, OutputInterface $output, $allow_production = FALSE) {
    if (isset($this->sourceEnvironment)) {
      return $this->sourceEnvironment;
    }

    if ($input->getArgument('environmentId')) {
      $environment_id = $input->getArgument('environmentId');
      $chosen_environment = $this->getCloudEnvironment($environment_id);
    }
    else {
      $cloud_application_uuid = $this->determineCloudApplication();
      $cloud_application = $this->getCloudApplication($cloud_application_uuid);
      $output->writeln('Using Cloud Application <options=bold>' . $cloud_application->name . '</>');
      $acquia_cloud_client = $this->cloudApiClientService->getClient();
      $chosen_environment = $this->promptChooseEnvironment($acquia_cloud_client, $cloud_application_uuid, $allow_production);
    }
    $this->logger->debug("Using environment {$chosen_environment->label} {$chosen_environment->uuid}");

    $this->sourceEnvironment = $chosen_environment;

    return $this->sourceEnvironment;
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   */
  protected function checkEnvironmentPhpVersions(EnvironmentResponse $environment): void {
    if (!$this->environmentPhpVersionMatches($environment)) {
      $this->io->warning("You are using PHP version {$this->getIdePhpVersion()} but the upstream environment {$environment->label} is using PHP version {$environment->configuration->php->version}");
    }
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $chosen_environment
   *
   * @throws \Exception
   */
  protected function matchIdePhpVersion(
    OutputInterface $output,
    EnvironmentResponse $chosen_environment
  ): void {
    if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !$this->environmentPhpVersionMatches($chosen_environment)) {
      $answer = $this->io->confirm("Would you like to change the PHP version on this IDE to match the PHP version on the <bg=cyan;options=bold>{$chosen_environment->label} ({$chosen_environment->configuration->php->version})</> environment?", FALSE);
      if ($answer) {
        $command = $this->getApplication()->find('ide:php-version');
        $command->run(new ArrayInput(['version' => $chosen_environment->configuration->php->version]),
          $output);
      }
    }
  }

  /**
   * @param $environment
   *
   * @return bool
   */
  private function environmentPhpVersionMatches($environment): bool {
    $current_php_version = $this->getIdePhpVersion();
    return $environment->configuration->php->version === $current_php_version;
  }

  /**
   * @param $input
   * @param \Closure $output_callback
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function executeAllScripts($input, Closure $output_callback): void {
    $this->setDirAndRequireProjectCwd($input);
    $this->runComposerScripts($output_callback);
    $this->runDrushCacheClear($output_callback);
    $this->runDrushSqlSanitize($output_callback);
  }

  /**
   * @param \Closure $output_callback
   *
   * @throws \Exception
   */
  protected function runDrushCacheClear(Closure $output_callback): void {
    if ($this->getDrushDatabaseConnectionStatus()) {
      $this->checklist->addItem('Clearing Drupal caches via Drush');
      // @todo Add support for Drush 8.
      $process = $this->localMachineHelper->execute([
        'drush',
        'cache:rebuild',
        '--yes',
        '--no-interaction',
        '--verbose',
      ], $output_callback, $this->dir, FALSE);
      if (!$process->isSuccessful()) {
        throw new AcquiaCliException('Unable to rebuild Drupal caches via Drush. {message}', ['message' => $process->getErrorOutput()]);
      }
      $this->checklist->completePreviousItem();
    }
    else {
      $this->logger->notice('Drush does not have an active database connection. Skipping cache:rebuild');
    }
  }

  /**
   * @param \Closure $output_callback
   *
   * @throws \Exception
   */
  protected function runDrushSqlSanitize(Closure $output_callback): void {
    if ($this->getDrushDatabaseConnectionStatus()) {
      $this->checklist->addItem('Sanitizing database via Drush');
      $process = $this->localMachineHelper->execute([
        'drush',
        'sql:sanitize',
        '--yes',
        '--no-interaction',
        '--verbose',
      ], $output_callback, $this->dir, FALSE);
      if (!$process->isSuccessful()) {
        throw new AcquiaCliException('Unable to sanitize Drupal database via Drush. {message}', ['message' => $process->getErrorOutput()]);
      }
      $this->checklist->completePreviousItem();
      $this->io->newLine();
      $this->io->text('Your database was sanitized via <options=bold>drush sql:sanitize</>. This has changed all user passwords to randomly generated strings. To log in to your Drupal site, use <options=bold>drush uli</>');
    }
    else {
      $this->logger->notice('Drush does not have an active database connection. Skipping sql:sanitize.');
    }
  }

  /**
   * @param $output_callback
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function composerInstall($output_callback): void {
    $process = $this->localMachineHelper->execute([
      'composer',
      'install',
      '--no-interaction',
    ], $output_callback, $this->dir, FALSE, NULL);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to install Drupal dependencies via Composer. {message}',
        ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param $environment
   * @param $database
   *
   * @return string
   */
  protected function getHostFromDatabaseResponse($environment, $database): string {
    if ($this->isAcsfEnv($environment)) {
      return $database->db_host . '.enterprise-g1.hosting.acquia.com';
    }
    else {
      return $database->db_host;
    }
  }

  /**
   * @param $environment
   * @param $database
   * @param string $filename
   *
   * @return string
   */
  protected function getRemoteTempFilepath($environment, $database, string $filename): string {
    if ($this->isAcsfEnv($environment)) {
      $ssh_url_parts = explode('.', $database->ssh_host);
      $temp_prefix = reset($ssh_url_parts);
    }
    else {
      $vcs_url_parts = explode('@', $environment->vcs->url);
      $sitegroup = $vcs_url_parts[0];
      $temp_prefix = $sitegroup . '.' . $database->environment->name;
    }

    return '/mnt/tmp/' . $temp_prefix;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param $environment
   * @param $database
   *
   * @return false|mixed
   */
  private function getDatabaseBackup(
    Client $acquia_cloud_client,
    $environment,
    $database
  ) {
    $database_backups = new DatabaseBackups($acquia_cloud_client);
    $backups_response = $database_backups->getAll($environment->uuid, $database->name);
    if (!count($backups_response)) {
      $this->logger->warning('No existing backups found, creating an on-demand backup now. This will take some time depending on the size of the database.');
      $this->createBackup($environment, $database, $acquia_cloud_client);
      $backups_response = $database_backups->getAll($environment->uuid,
        $database->name);
    }
    $backup_response = $backups_response[0];
    $this->logger->debug('Using database backup (id #' . $backup_response->id . ') generated at ' . $backup_response->completedAt);

    return $backup_response;
  }

  /**
   * Print information to the console about the selected database backup.
   *
   * @param BackupResponse $backup_response
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $source_environment
   */
  private function printDatabaseBackupInfo(
    BackupResponse $backup_response,
    EnvironmentResponse $source_environment
  ): void {
    $interval = time() - strtotime($backup_response->completedAt);
    $hours_interval = floor($interval / 60 / 60);
    $date_formatted = date("D M j G:i:s T Y", strtotime($backup_response->completedAt));
    $web_link = "https://cloud.acquia.com/a/environments/{$source_environment->uuid}/databases";
    $messages = [
      "Using a database backup that is $hours_interval hours old. Backup #{$backup_response->id} was created at {$date_formatted}.",
      "You can view your backups here: {$web_link}",
      "To generate a new backup, re-run this command with the --on-demand option."
    ];
    if ($hours_interval > 24) {
      $this->io->warning($messages);
    }
    else {
      $this->io->info($messages);
    }
  }

  /**
   * @param \stdClass $database
   * @param string $local_filepath
   * @param callable|null $output_callback
   *
   * @throws \Exception
   */
  private function importRemoteDatabase(stdClass $database, string $local_filepath, callable $output_callback = NULL): void {
    if ($database->flags->default) {
      $this->doImportRemoteDatabase($this->getDefaultLocalDbHost(), $this->getDefaultLocalDbUser(), $this->getDefaultLocalDbName(), $this->getDefaultLocalDbPassword(), $local_filepath, $output_callback);
    }
    elseif (AcquiaDrupalEnvironmentDetector::isAhIdeEnv() || AcquiaDrupalEnvironmentDetector::isLandoEnv()) {
      $this->io->note("Acquia CLI assumes that the database name for the {$database->name} database is also {$database->name}");
      $this->doImportRemoteDatabase($this->getDefaultLocalDbHost(), 'root', $database->name, '', $local_filepath, $output_callback);
    }
    elseif (AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !getenv('IDE_ENABLE_MULTISITE')) {
      // Cloud IDE only has 2 available databases for importing, so we only allow importing into the default database.
      $this->io->note("Cloud IDE only supports importing into the default Drupal database. Acquia CLI will import the NON-DEFAULT database {$database->name} into the DEFAULT database {$this->getDefaultLocalDbName()}");
      $this->doImportRemoteDatabase($this->getDefaultLocalDbHost(), $this->getDefaultLocalDbUser(), $this->getDefaultLocalDbName(), $this->getDefaultLocalDbPassword(), $local_filepath, $output_callback);
    }
    else {
      $this->io->note("Acquia CLI assumes that the local database name for the {$database->name} database is also {$database->name}");
      $this->doImportRemoteDatabase($this->getDefaultLocalDbHost(), $this->getDefaultLocalDbUser(), $database->name, $this->getDefaultLocalDbPassword(), $local_filepath, $output_callback);
    }
  }

}
