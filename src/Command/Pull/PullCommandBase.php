<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Pull;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\IdeCommandTrait;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\DatabaseBackups;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Response\BackupResponse;
use AcquiaCloudApi\Response\DatabaseResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Closure;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

abstract class PullCommandBase extends CommandBase {

  use IdeCommandTrait;

  protected Checklist $checklist;

  private string $site;

  private UriInterface $backupDownloadUrl;

  public function __construct(
    public LocalMachineHelper $localMachineHelper,
    protected CloudDataStore $datastoreCloud,
    protected AcquiaCliDatastore $datastoreAcli,
    protected ApiCredentialsInterface $cloudCredentials,
    protected TelemetryHelper $telemetryHelper,
    protected string $projectDir,
    protected ClientService $cloudApiClientService,
    public SshHelper $sshHelper,
    protected string $sshDir,
    LoggerInterface $logger,
    protected \GuzzleHttp\Client $httpClient
  ) {
    parent::__construct($this->localMachineHelper, $this->datastoreCloud, $this->datastoreAcli, $this->cloudCredentials, $this->telemetryHelper, $this->projectDir, $this->cloudApiClientService, $this->sshHelper, $this->sshDir, $logger);
  }

  /**
   * @see https://github.com/drush-ops/drush/blob/c21a5a24a295cc0513bfdecead6f87f1a2cf91a2/src/Sql/SqlMysql.php#L168
   * @return string[]
   */
  private function listTables(string $out): array {
    $tables = [];
    if ($out = trim($out)) {
      $tables = explode(PHP_EOL, $out);
    }
    return $tables;
  }

  /**
   * @see https://github.com/drush-ops/drush/blob/c21a5a24a295cc0513bfdecead6f87f1a2cf91a2/src/Sql/SqlMysql.php#L178
   * @return string[]
   */
  private function listTablesQuoted(string $out): array {
    $tables = $this->listTables($out);
    foreach ($tables as &$table) {
      $table = "`$table`";
    }
    return $tables;
  }

  public static function getBackupPath(object $environment, DatabaseResponse $database, object $backupResponse): string {
    // Databases have a machine name not exposed via the API; we can only
    // approximately reconstruct it and match the filename you'd get downloading
    // a backup from Cloud UI.
    if ($database->flags->default) {
      $dbMachineName = $database->name . $environment->name;
    }
    else {
      $dbMachineName = 'db' . $database->id;
    }
    $filename = implode('-', [
        $environment->name,
        $database->name,
        $dbMachineName,
        $backupResponse->completedAt,
      ]) . '.sql.gz';
    return Path::join(sys_get_temp_dir(), $filename);
  }

  protected function initialize(InputInterface $input, OutputInterface $output): void {
    parent::initialize($input, $output);
    $this->checklist = new Checklist($output);
  }

  protected function pullCode(InputInterface $input, OutputInterface $output, bool $clone, EnvironmentResponse $sourceEnvironment): void {
    if ($clone) {
      $this->checklist->addItem('Cloning git repository from the Cloud Platform');
      $this->cloneFromCloud($sourceEnvironment, $this->getOutputCallback($output, $this->checklist));
    }
    else {
      $this->checklist->addItem('Pulling code from the Cloud Platform');
      $this->pullCodeFromCloud($sourceEnvironment, $this->getOutputCallback($output, $this->checklist));
    }
    $this->checklist->completePreviousItem();
  }

  /**
   * @param bool $onDemand Force on-demand backup.
   * @param bool $noImport Skip import.
   */
  protected function pullDatabase(InputInterface $input, OutputInterface $output, EnvironmentResponse $sourceEnvironment, bool $onDemand = FALSE, bool $noImport = FALSE, bool $multipleDbs = FALSE): void {
    $this->setDirAndRequireProjectCwd($input);
    if (!$noImport) {
      // Verify database connection.
      $this->connectToLocalDatabase($this->getLocalDbHost(), $this->getLocalDbUser(), $this->getLocalDbName(), $this->getLocalDbPassword(), $this->getOutputCallback($output, $this->checklist));
    }
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $site = $this->determineSite($sourceEnvironment, $input);
    $databases = $this->determineCloudDatabases($acquiaCloudClient, $sourceEnvironment, $site, $multipleDbs);

    foreach ($databases as $database) {
      if ($onDemand) {
        $this->checklist->addItem("Creating an on-demand database(s) backup on Cloud Platform");
        $this->createBackup($sourceEnvironment, $database, $acquiaCloudClient);
        $this->checklist->completePreviousItem();
      }
      $backupResponse = $this->getDatabaseBackup($acquiaCloudClient, $sourceEnvironment, $database);
      if (!$onDemand) {
        $this->printDatabaseBackupInfo($backupResponse, $sourceEnvironment);
      }

      $this->checklist->addItem("Downloading $database->name database copy from the Cloud Platform");
      $localFilepath = $this->downloadDatabaseBackup($sourceEnvironment, $database, $backupResponse, $this->getOutputCallback($output, $this->checklist));
      $this->checklist->completePreviousItem();

      if ($noImport) {
        $this->io->success("$database->name database backup downloaded to $localFilepath");
      }
      else {
        $this->checklist->addItem("Importing $database->name database download");
        $this->importRemoteDatabase($database, $localFilepath, $this->getOutputCallback($output, $this->checklist));
        $this->checklist->completePreviousItem();
      }
    }
  }

  protected function pullFiles(InputInterface $input, OutputInterface $output, EnvironmentResponse $sourceEnvironment): void {
    $this->checklist->addItem('Copying Drupal\'s public files from the Cloud Platform');
    $site = $this->determineSite($sourceEnvironment, $input);
    $this->rsyncFilesFromCloud($sourceEnvironment, $this->getOutputCallback($output, $this->checklist), $site);
    $this->checklist->completePreviousItem();
  }

  private function pullCodeFromCloud(EnvironmentResponse $chosenEnvironment, Closure $outputCallback = NULL): void {
    $isDirty = $this->isLocalGitRepoDirty();
    if ($isDirty) {
      throw new AcquiaCliException('Pulling code from your Cloud Platform environment was aborted because your local Git repository has uncommitted changes. Either commit, reset, or stash your changes via git.');
    }
    // @todo Validate that an Acquia remote is configured for this repository.
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $this->localMachineHelper->execute([
      'git',
      'fetch',
      '--all',
    ], $outputCallback, $this->dir, FALSE);
    $this->checkoutBranchFromEnv($chosenEnvironment, $outputCallback);
  }

  /**
   * Checks out the matching branch from a source environment.
   */
  private function checkoutBranchFromEnv(EnvironmentResponse $environment, Closure $outputCallback = NULL): void {
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $this->localMachineHelper->execute([
      'git',
      'checkout',
      $environment->vcs->path,
    ], $outputCallback, $this->dir, FALSE);
  }

  private function doImportRemoteDatabase(
    string $databaseHost,
    string $databaseUser,
    string $databaseName,
    string $databasePassword,
    string $localFilepath,
    Closure $outputCallback = NULL
  ): void {
    $this->dropDbTables($databaseHost, $databaseUser, $databaseName, $databasePassword, $outputCallback);
    $this->importDatabaseDump($localFilepath, $databaseHost, $databaseUser, $databaseName, $databasePassword, $outputCallback);
    $this->localMachineHelper->getFilesystem()->remove($localFilepath);
  }

  private function downloadDatabaseBackup(
    EnvironmentResponse $environment,
    DatabaseResponse $database,
    BackupResponse $backupResponse,
    callable $outputCallback = NULL
  ): string {
    if ($outputCallback) {
      $outputCallback('out', "Downloading backup $backupResponse->id");
    }
    $localFilepath = self::getBackupPath($environment, $database, $backupResponse);
    if ($this->output instanceof ConsoleOutput) {
      $output = $this->output->section();
    }
    else {
      $output = $this->output;
    }
    // These options tell curl to stream the file to disk rather than loading it into memory.
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $acquiaCloudClient->addOption('sink', $localFilepath);
    $acquiaCloudClient->addOption('curl.options', [
      'CURLOPT_FILE' => $localFilepath,
      'CURLOPT_RETURNTRANSFER' => FALSE,
]);
    $acquiaCloudClient->addOption('progress', static function (mixed $totalBytes, mixed $downloadedBytes) use (&$progress, $output): void {
      self::displayDownloadProgress($totalBytes, $downloadedBytes, $progress, $output);
    });
    // This is really just used to allow us to inject values for $url during testing.
    // It should be empty during normal operations.
    $url = $this->getBackupDownloadUrl();
    $acquiaCloudClient->addOption('on_stats', function (TransferStats $stats) use (&$url): void {
      $url = $stats->getEffectiveUri();
    });

    try {
      $acquiaCloudClient->stream("get", "/environments/$environment->uuid/databases/$database->name/backups/$backupResponse->id/actions/download", $acquiaCloudClient->getOptions());
      return $localFilepath;
    }
    catch (RequestException $exception) {
      // Deal with broken SSL certificates.
      // @see https://timi.eu/docs/anatella/5_1_9_1_list_of_curl_error_codes.html
      if (in_array($exception->getHandlerContext()['errno'], [51, 60], TRUE)) {
        $outputCallback('out', '<comment>The certificate for ' . $url->getHost() . ' is invalid.</comment>');
        assert($url !== NULL);
        $domainsResource = new Domains($this->cloudApiClientService->getClient());
        $domains = $domainsResource->getAll($environment->uuid);
        foreach ($domains as $domain) {
          if ($domain->hostname === $url->getHost()) {
            continue;
          }
          $outputCallback('out', '<comment>Trying alternative host ' . $domain->hostname . ' </comment>');
          $downloadUrl = $url->withHost($domain->hostname);
          try {
            $this->httpClient->request('GET', $downloadUrl, ['sink' => $localFilepath]);
            return $localFilepath;
          }
          catch (Exception) {
            // Continue in the foreach() loop.
          }
        }
      }
    }

    // If we looped through all domains and got here, we didn't download anything.
    throw new AcquiaCliException('Could not download backup');
  }

  public function setBackupDownloadUrl(UriInterface $url): void {
    $this->backupDownloadUrl = $url;
  }

  private function getBackupDownloadUrl(): ?UriInterface {
    return $this->backupDownloadUrl ?? NULL;
  }

  public static function displayDownloadProgress(mixed $totalBytes, mixed $downloadedBytes, mixed &$progress, OutputInterface $output): void {
    if ($totalBytes > 0 && is_null($progress)) {
      $progress = new ProgressBar($output, $totalBytes);
      $progress->setFormat('        %current%/%max% [%bar%] %percent:3s%%');
      $progress->setProgressCharacter('💧');
      $progress->setOverwrite(TRUE);
      $progress->start();
    }

    if (!is_null($progress)) {
      if ($totalBytes === $downloadedBytes && $progress->getProgressPercent() !== 1.0) {
        $progress->finish();
        if ($output instanceof ConsoleSectionOutput) {
          $output->clear();
        }
        return;
      }
      $progress->setProgress($downloadedBytes);
    }
  }

  /**
   * Create an on-demand backup and wait for it to become available.
   */
  private function createBackup(EnvironmentResponse $environment, DatabaseResponse $database, Client $acquiaCloudClient): void {
    $backups = new DatabaseBackups($acquiaCloudClient);
    $response = $backups->create($environment->uuid, $database->name);
    $urlParts = explode('/', $response->links->notification->href);
    $notificationUuid = end($urlParts);
    $this->waitForBackup($notificationUuid, $acquiaCloudClient);
  }

  /**
   * Wait for an on-demand backup to become available (Cloud API notification).
   *
   * @infection-ignore-all
   */
  protected function waitForBackup(string $notificationUuid, Client $acquiaCloudClient): void {
    $spinnerMessage = 'Waiting for database backup to complete...';
    $successCallback = function (): void {
      $this->output->writeln('');
      $this->output->writeln('<info>Database backup is ready!</info>');
    };
    $this->waitForNotificationToComplete($acquiaCloudClient, $notificationUuid, $spinnerMessage, $successCallback);
    Loop::run();
  }

  private function connectToLocalDatabase(string $dbHost, string $dbUser, string $dbName, string $dbPassword, callable $outputCallback = NULL): void {
    if ($outputCallback) {
      $outputCallback('out', "Connecting to database $dbName");
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['mysql']);
    $command = [
      'mysql',
      '--host',
      $dbHost,
      '--user',
      $dbUser,
      $dbName,
    ];
    $process = $this->localMachineHelper->execute($command, $outputCallback, NULL, FALSE, NULL, ['MYSQL_PWD' => $dbPassword]);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to connect to local database using credentials mysql://{user}:{password}@{host}/{database}. {message}', [
        'database' => $dbName,
        'host' => $dbHost,
        'message' => $process->getErrorOutput(),
        'password' => $dbPassword,
        'user' => $dbUser,
      ]);
    }
  }

  private function dropDbTables(string $dbHost, string $dbUser, string $dbName, string $dbPassword, ?\Closure $outputCallback = NULL): void {
    if ($outputCallback) {
      $outputCallback('out', "Dropping tables from database $dbName");
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['mysql']);
    $command = [
      'mysql',
      '--host',
      $dbHost,
      '--user',
      $dbUser,
      $dbName,
      '--silent',
      '-e',
      'SHOW TABLES;',
    ];
    $process = $this->localMachineHelper->execute($command, $outputCallback, NULL, FALSE, NULL, ['MYSQL_PWD' => $dbPassword]);
    $tables = $this->listTablesQuoted($process->getOutput());
    if ($tables) {
      $sql = 'DROP TABLE ' . implode(', ', $tables);
      $tempnam = $this->localMachineHelper->getFilesystem()->tempnam(sys_get_temp_dir(), 'acli_drop_table_', '.sql');
      $this->localMachineHelper->getFilesystem()->dumpFile($tempnam, $sql);
      $command = [
        'mysql',
        '--host',
        $dbHost,
        '--user',
        $dbUser,
        $dbName,
        '-e',
        'source ' . $tempnam,
      ];
      $process = $this->localMachineHelper->execute($command, $outputCallback, NULL, FALSE, NULL, ['MYSQL_PWD' => $dbPassword]);
      if (!$process->isSuccessful()) {
        throw new AcquiaCliException('Unable to drop tables from database. {message}', ['message' => $process->getErrorOutput()]);
      }
    }
  }

  private function importDatabaseDump(string $localDumpFilepath, string $dbHost, string $dbUser, string $dbName, string $dbPassword, Closure $outputCallback = NULL): void {
    if ($outputCallback) {
      $outputCallback('out', "Importing downloaded file to database $dbName");
    }
    $this->logger->debug("Importing $localDumpFilepath to MySQL on local machine");
    $this->localMachineHelper->checkRequiredBinariesExist(['gunzip', 'mysql']);
    if ($this->localMachineHelper->commandExists('pv')) {
      $command = "pv $localDumpFilepath --bytes --rate | gunzip | MYSQL_PWD=$dbPassword mysql --host=$dbHost --user=$dbUser $dbName";
    }
    else {
      $this->io->warning('Install `pv` to see progress bar');
      $command = "gunzip -c $localDumpFilepath | MYSQL_PWD=$dbPassword mysql --host=$dbHost --user=$dbUser $dbName";
    }

    $process = $this->localMachineHelper->executeFromCmd($command, $outputCallback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to import local database. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  private function determineSite(string|EnvironmentResponse|array $environment, InputInterface $input): mixed {
    if (isset($this->site)) {
      return $this->site;
    }

    if ($input->hasArgument('site') && $input->getArgument('site')) {
      return $input->getArgument('site');
    }

    if ($this->isAcsfEnv($environment)) {
      $site = $this->promptChooseAcsfSite($environment);
    }
    else {
      $site = $this->promptChooseCloudSite($environment);
    }
    $this->site = $site;

    return $site;
  }

  private function rsyncFilesFromCloud(EnvironmentResponse $chosenEnvironment, Closure $outputCallback, string $site): void {
    $sourceDir = $chosenEnvironment->sshUrl . ':' . $this->getCloudFilesDir($chosenEnvironment, $site);
    $destinationDir = $this->getLocalFilesDir($site);
    $this->localMachineHelper->getFilesystem()->mkdir($destinationDir);

    $this->rsyncFiles($sourceDir, $destinationDir, $outputCallback);
  }

  protected function determineCloneProject(OutputInterface $output): bool {
    $finder = $this->localMachineHelper->getFinder()->files()->in($this->dir)->ignoreDotFiles(FALSE);

    // If we are in an IDE, assume we should pull into /home/ide/project.
    if ($this->dir === '/home/ide/project' && AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !$finder->hasResults()) {
      $output->writeln('Cloning into current directory.');
      return TRUE;
    }

    // If $this->projectDir is set, pull into that dir rather than cloning.
    if ($this->projectDir) {
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

    throw new AcquiaCliException('Execute this command from within a Drupal project directory or an empty directory');
  }

  private function cloneFromCloud(EnvironmentResponse $chosenEnvironment, Closure $outputCallback): void {
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $command = [
      'git',
      'clone',
      $chosenEnvironment->vcs->url,
      $this->dir,
    ];
    $process = $this->localMachineHelper->execute($command, $outputCallback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL), NULL, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=no']);
    $this->checkoutBranchFromEnv($chosenEnvironment, $outputCallback);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Failed to clone repository from the Cloud Platform: {message}', ['message' => $process->getErrorOutput()]);
    }
    $this->projectDir = $this->dir;
  }

  protected function checkEnvironmentPhpVersions(EnvironmentResponse $environment): void {
    $version = $this->getIdePhpVersion();
    if (empty($version)) {
      $this->io->warning("Could not determine current PHP version. Set it by running acli ide:php-version.");
    }
    else if (!$this->environmentPhpVersionMatches($environment)) {
      $this->io->warning("You are using PHP version $version but the upstream environment $environment->label is using PHP version {$environment->configuration->php->version}");
    }
  }

  protected function matchIdePhpVersion(
    OutputInterface $output,
    EnvironmentResponse $chosenEnvironment
  ): void {
    if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !$this->environmentPhpVersionMatches($chosenEnvironment)) {
      $answer = $this->io->confirm("Would you like to change the PHP version on this IDE to match the PHP version on the <bg=cyan;options=bold>$chosenEnvironment->label ({$chosenEnvironment->configuration->php->version})</> environment?", FALSE);
      if ($answer) {
        $command = $this->getApplication()->find('ide:php-version');
        $command->run(new ArrayInput(['command' => 'ide:php-version', 'version' => $chosenEnvironment->configuration->php->version]),
          $output);
      }
    }
  }

  private function environmentPhpVersionMatches(EnvironmentResponse $environment): bool {
    $currentPhpVersion = $this->getIdePhpVersion();
    return $environment->configuration->php->version === $currentPhpVersion;
  }

  private function getDatabaseBackup(
    Client $acquiaCloudClient,
    string|EnvironmentResponse|array $environment,
    DatabaseResponse $database
  ): BackupResponse {
    $databaseBackups = new DatabaseBackups($acquiaCloudClient);
    $backupsResponse = $databaseBackups->getAll($environment->uuid, $database->name);
    if (!count($backupsResponse)) {
      $this->io->warning('No existing backups found, creating an on-demand backup now. This will take some time depending on the size of the database.');
      $this->createBackup($environment, $database, $acquiaCloudClient);
      $backupsResponse = $databaseBackups->getAll($environment->uuid,
        $database->name);
    }
    $backupResponse = $backupsResponse[0];
    $this->logger->debug('Using database backup (id #' . $backupResponse->id . ') generated at ' . $backupResponse->completedAt);

    return $backupResponse;
  }

  /**
   * Print information to the console about the selected database backup.
   */
  private function printDatabaseBackupInfo(
    BackupResponse $backupResponse,
    EnvironmentResponse $sourceEnvironment
  ): void {
    $interval = time() - strtotime($backupResponse->completedAt);
    $hoursInterval = floor($interval / 60 / 60);
    $dateFormatted = date("D M j G:i:s T Y", strtotime($backupResponse->completedAt));
    $webLink = "https://cloud.acquia.com/a/environments/{$sourceEnvironment->uuid}/databases";
    $messages = [
      "Using a database backup that is $hoursInterval hours old. Backup #$backupResponse->id was created at {$dateFormatted}.",
      "You can view your backups here: $webLink",
      "To generate a new backup, re-run this command with the --on-demand option.",
    ];
    if ($hoursInterval > 24) {
      $this->io->warning($messages);
    }
    else {
      $this->io->info($messages);
    }
  }

  private function importRemoteDatabase(DatabaseResponse $database, string $localFilepath, Closure $outputCallback = NULL): void {
    if ($database->flags->default) {
      // Easy case, import the default db into the default db.
      $this->doImportRemoteDatabase($this->getLocalDbHost(), $this->getLocalDbUser(), $this->getLocalDbName(), $this->getLocalDbPassword(), $localFilepath, $outputCallback);
    }
    else if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !getenv('IDE_ENABLE_MULTISITE')) {
      // Import non-default db into default db. Needed on legacy IDE without multiple dbs.
      // @todo remove this case once all IDEs support multiple dbs.
      $this->io->note("Cloud IDE only supports importing into the default Drupal database. Acquia CLI will import the NON-DEFAULT database {$database->name} into the DEFAULT database {$this->getLocalDbName()}");
      $this->doImportRemoteDatabase($this->getLocalDbHost(), $this->getLocalDbUser(), $this->getLocalDbName(), $this->getLocalDbPassword(), $localFilepath, $outputCallback);
    }
    else {
      // Import non-default db into non-default db.
      $this->io->note("Acquia CLI assumes that the local name for the {$database->name} database is also {$database->name}");
      if (AcquiaDrupalEnvironmentDetector::isLandoEnv() || AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
        $this->doImportRemoteDatabase($this->getLocalDbHost(), 'root', $database->name, '', $localFilepath, $outputCallback);
      }
      else {
        $this->doImportRemoteDatabase($this->getLocalDbHost(), $this->getLocalDbUser(), $database->name, $this->getLocalDbPassword(), $localFilepath, $outputCallback);
      }
    }
  }

}
