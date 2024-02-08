<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Command\Pull\PullDatabaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use AcquiaCloudApi\Response\BackupsResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @property \Acquia\Cli\Command\Pull\PullDatabaseCommand $command
 */
class PullDatabaseCommandTest extends PullCommandTestBase {

  protected string $dbUser = 'drupal';
  protected string $dbPassword = 'drupal';
  protected string $dbHost = 'localhost';
  protected string $dbName = 'drupal';

  public function setUp(): void {
    self::unsetEnvVars(['ACLI_DB_HOST', 'ACLI_DB_USER', 'ACLI_DB_PASSWORD', 'ACLI_DB_NAME']);
    parent::setUp();
  }

  /**
   * @return int[][]
   */
  public function providerTestPullDatabaseWithInvalidSslCertificate(): array {
    return [[51], [60]];
  }

  public function mockGetBackup(mixed $environment): void {
    $databases = $this->mockRequest('getEnvironmentsDatabases', $environment->id);
    $tamper = function ($backups): void {
      $backups[0]->completedAt = $backups[0]->completed_at;
    };
    $backups = new BackupsResponse(
      $this->mockRequest('getEnvironmentsDatabaseBackups', [
        $environment->id,
        'my_db',
      ], NULL, NULL, $tamper)
    );
    $this->mockDownloadBackup($databases[0], $environment, $backups[0]);
  }

  protected function createCommand(): CommandBase {
    $this->httpClientProphecy = $this->prophet->prophesize(Client::class);

    return new PullDatabaseCommand(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->acliRepoRoot,
      $this->clientServiceProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClientProphecy->reveal()
    );
  }

  public function testPullDatabases(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteMySqlConnect($localMachineHelper, TRUE);
    $environment = $this->mockGetEnvironment();
    $sshHelper = $this->mockSshHelper();
    $this->mockListSites($sshHelper);
    $this->mockGetBackup($environment);
    $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
    $fs = $this->prophet->prophesize(Filesystem::class);
    $this->mockExecuteMySqlDropDb($localMachineHelper, TRUE, $fs);
    $this->mockExecuteMySqlImport($localMachineHelper, TRUE, TRUE, 'my_db', 'my_dbdev', 'drupal');
    $fs->remove(Argument::type('string'))->shouldBeCalled();
    $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();

    $this->executeCommand([
    '--no-scripts' => TRUE,
    ], self::inputChooseEnvironment());

    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a database [my_db (default)]:', $output);
    $this->assertStringContainsString('Downloading backup 1', $output);
  }

  public function testPullDatabasesLocalConnectionFailure(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteMySqlConnect($localMachineHelper, FALSE);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Unable to connect');
    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], self::inputChooseEnvironment());
  }

  public function testPullDatabaseNoPv(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteMySqlConnect($localMachineHelper, TRUE);
    $environment = $this->mockGetEnvironment();
    $sshHelper = $this->mockSshHelper();
    $this->mockListSites($sshHelper);
    $this->mockGetBackup($environment);
    $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
    $fs = $this->prophet->prophesize(Filesystem::class);
    $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
    $this->mockExecuteMySqlDropDb($localMachineHelper, TRUE, $fs);
    $this->mockExecuteMySqlImport($localMachineHelper, TRUE, FALSE, 'my_db', 'my_dbdev', 'drupal');
    $fs->remove(Argument::type('string'))->shouldBeCalled();

    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], self::inputChooseEnvironment());

    $output = $this->getDisplay();

    $this->assertStringContainsString(' [WARNING] Install `pv` to see progress bar', $output);
  }

  public function testPullMultipleDatabases(): void {
    $this->setupPullDatabase(TRUE, TRUE, FALSE, TRUE, TRUE);
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      //  Choose a Cloud Platform environment [Dev, dev (vcs: master)]:
      0,
      //  Choose a site [jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)]:
      0,
      // Choose databases. You may choose multiple. Use commas to separate choices. [profserv2 (default)]:
      '10,27',
    ];
    $this->executeCommand([
      '--multiple-dbs' => TRUE,
      '--no-scripts' => TRUE,
    ], $inputs);

  }

  public function testPullDatabasesOnDemand(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE);
    $inputs = self::inputChooseEnvironment();

    $this->executeCommand([
      '--no-scripts' => TRUE,
      '--on-demand' => TRUE,
    ], $inputs);

    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a site [jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)]:', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
  }

  public function testPullDatabasesNoExistingBackup(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, TRUE, FALSE, 0, FALSE);
    $inputs = self::inputChooseEnvironment();

    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], $inputs);

    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a site [jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)]:', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
    $this->assertStringContainsString('No existing backups found, creating an on-demand backup now.', $output);
  }

  public function testPullDatabasesSiteArgument(): void {
    $this->setupPullDatabase(TRUE, TRUE, FALSE, FALSE);
    $inputs = self::inputChooseEnvironment();

    $this->executeCommand([
      '--no-scripts' => TRUE,
      'site' => 'jxr5000596dev',
    ], $inputs);

    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringNotContainsString('Choose a database', $output);
  }

  public function testPullDatabaseWithMySqlDropError(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteMySqlConnect($localMachineHelper, TRUE);
    $environment = $this->mockGetEnvironment();
    $sshHelper = $this->mockSshHelper();
    $this->mockListSites($sshHelper);
    $this->mockGetBackup($environment);
    $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
    $fs = $this->prophet->prophesize(Filesystem::class);
    $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
    $this->mockExecuteMySqlDropDb($localMachineHelper, FALSE, $fs);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Unable to drop tables from database');
    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], self::inputChooseEnvironment());
  }

  public function testPullDatabaseWithMySqlImportError(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteMySqlConnect($localMachineHelper, TRUE);
    $environment = $this->mockGetEnvironment();
    $sshHelper = $this->mockSshHelper();
    $this->mockListSites($sshHelper);
    $this->mockGetBackup($environment);
    $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
    $fs = $this->prophet->prophesize(Filesystem::class);
    $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
    $this->mockExecuteMySqlDropDb($localMachineHelper, TRUE, $fs);
    $this->mockExecuteMySqlImport($localMachineHelper, FALSE, TRUE, 'my_db', 'my_dbdev', 'drupal');

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Unable to import local database');
    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], self::inputChooseEnvironment());
  }

  /**
   * @dataProvider providerTestPullDatabaseWithInvalidSslCertificate
   */
  public function testPullDatabaseWithInvalidSslCertificate(int $errorCode): void {
    $this->setupPullDatabase(TRUE, FALSE, FALSE, TRUE, FALSE, $errorCode);
    $inputs = self::inputChooseEnvironment();

    $this->executeCommand(['--no-scripts' => TRUE], $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString('The certificate for www.example.com is invalid.', $output);
    $this->assertStringContainsString('Trying alternative host other.example.com', $output);
  }

  protected function setupPullDatabase(bool $mysqlConnectSuccessful, bool $mockIdeFs = FALSE, bool $onDemand = FALSE, bool $mockGetAcsfSites = TRUE, bool $multiDb = FALSE, int $curlCode = 0, bool $existingBackups = TRUE): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockAcsfEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $this->createMockGitConfigFile();

    $databasesResponse = $this->mockAcsfDatabasesResponse($selectedEnvironment);
    $databaseResponse = $databasesResponse[array_search('jxr5000596dev', array_column($databasesResponse, 'name'), TRUE)];
    $databaseBackupsResponse = $this->mockDatabaseBackupsResponse($selectedEnvironment, $databaseResponse->name, 1, $existingBackups);
    $selectedDatabase = $this->mockDownloadBackup($databaseResponse, $selectedEnvironment, $databaseBackupsResponse->_embedded->items[0], $curlCode);

    if ($multiDb) {
      $databaseResponse2 = $databasesResponse[array_search('profserv2', array_column($databasesResponse, 'name'), TRUE)];
      $databaseBackupsResponse2 = $this->mockDatabaseBackupsResponse($selectedEnvironment, $databaseResponse2->name, 1, $existingBackups);
      $this->mockDownloadBackup($databaseResponse2, $selectedEnvironment, $databaseBackupsResponse2->_embedded->items[0], $curlCode);
    }

    $sshHelper = $this->mockSshHelper();
    if ($mockGetAcsfSites) {
      $this->mockGetAcsfSites($sshHelper);
    }

    if ($onDemand) {
      $backupResponse = $this->mockDatabaseBackupCreateResponse($selectedEnvironment, $selectedDatabase->name);
      // Cloud API does not provide the notification UUID as part of the backup response, so we must hardcode it.
      $this->mockNotificationResponseFromObject($backupResponse);
    }

    $fs = $this->prophet->prophesize(Filesystem::class);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteMySqlConnect($localMachineHelper, $mysqlConnectSuccessful);
    // Set up file system.
    $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();

    // Mock IDE filesystem.
    if ($mockIdeFs) {
      $this->mockDrupalSettingsRefresh($localMachineHelper);
    }
    $this->mockSettingsFiles($fs);

    // Database.
    $this->mockExecuteMySqlListTables($localMachineHelper);
    $this->mockExecuteMySqlDropDb($localMachineHelper, TRUE, $fs);
    $this->mockExecuteMySqlImport($localMachineHelper, TRUE, TRUE);
    if ($multiDb) {
      $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
      $this->mockExecuteMySqlImport($localMachineHelper, TRUE, TRUE, 'profserv2', 'profserv2dev', 'drupal');
    }
  }

  protected function mockSshHelper(): ObjectProphecy|SshHelper {
    $sshHelper = parent::mockSshHelper();
    $this->command->sshHelper = $sshHelper->reveal();
    return $sshHelper;
  }

  private function mockListSites(SshHelper|ObjectProphecy $sshHelper): void {
    $process = $this->mockProcess();
    $process->getOutput()->willReturn('default')->shouldBeCalled();
    $sshHelper->executeCommand(Argument::type('object'), ['ls', '/mnt/files/site.dev/sites'], FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockExecuteMySqlConnect(
    ObjectProphecy $localMachineHelper,
    bool $success
  ): void {
    $localMachineHelper->checkRequiredBinariesExist(["mysql"])->shouldBeCalled();
    $process = $this->mockProcess($success);
    $localMachineHelper
      ->execute([
        'mysql',
        '--host',
        $this->dbHost,
        '--user',
        'drupal',
        'drupal',
      ], Argument::type('callable'), NULL, FALSE, NULL, ['MYSQL_PWD' => 'drupal'])
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteMySqlListTables(
    LocalMachineHelper|ObjectProphecy $localMachineHelper,
    string $dbName = 'jxr5000596dev',
  ): void {
    $localMachineHelper->checkRequiredBinariesExist(["mysql"])->shouldBeCalled();
    $process = $this->mockProcess();
    $process->getOutput()->willReturn('table1');
    $command = [
      'mysql',
      '--host',
      'localhost',
      '--user',
      'drupal',
      $dbName,
      '--silent',
      '-e',
      'SHOW TABLES;',
    ];
    $localMachineHelper
      ->execute($command, Argument::type('callable'), NULL, FALSE, NULL, ['MYSQL_PWD' => $this->dbPassword])
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteMySqlDropDb(
    LocalMachineHelper|ObjectProphecy $localMachineHelper,
    bool $success,
    ObjectProphecy $fs
  ): void {
    $localMachineHelper->checkRequiredBinariesExist(["mysql"])->shouldBeCalled();
    $process = $this->mockProcess($success);
    $fs->tempnam(Argument::type('string'), 'acli_drop_table_', '.sql')->willReturn('something')->shouldBeCalled();
    $fs->dumpfile('something', Argument::type('string'))->shouldBeCalled();
    $localMachineHelper
      ->execute(Argument::type('array'), Argument::type('callable'), NULL, FALSE, NULL, ['MYSQL_PWD' => $this->dbPassword])
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteMySqlImport(
    ObjectProphecy $localMachineHelper,
    bool $success,
    bool $pvExists,
    string $dbName = 'jxr5000596dev',
    string $dbMachineName = 'db554675',
    string $localDbName = 'jxr5000596dev'
  ): void {
    $localMachineHelper->checkRequiredBinariesExist(['gunzip', 'mysql'])->shouldBeCalled();
    $this->mockExecutePvExists($localMachineHelper, $pvExists);
    $process = $this->mockProcess($success);
    $filePath = Path::join(sys_get_temp_dir(), "dev-$dbName-$dbMachineName-2012-05-15T12:00:00Z.sql.gz");
    $command = $pvExists ? "pv $filePath --bytes --rate | gunzip | MYSQL_PWD=drupal mysql --host=localhost --user=drupal $localDbName" : "gunzip -c $filePath | MYSQL_PWD=drupal mysql --host=localhost --user=drupal $localDbName";
    // MySQL import command.
    $localMachineHelper
      ->executeFromCmd($command, Argument::type('callable'),
        NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockDownloadMySqlDump(ObjectProphecy $localMachineHelper, mixed $success): void {
    $process = $this->mockProcess($success);
    $localMachineHelper->writeFile(
      Argument::containingString("dev-profserv2-profserv201dev-something.sql.gz"),
      'backupfilecontents'
    )
      ->shouldBeCalled();
  }

  protected function mockSettingsFiles(ObjectProphecy $fs): void {
    $fs->remove(Argument::type('string'))
      ->willReturn()
      ->shouldBeCalled();
  }

  public function testDownloadProgressDisplay(): void {
    $output = new BufferedOutput();
    $progress = NULL;
    PullCommandBase::displayDownloadProgress(100, 0, $progress, $output);
    $this->assertStringContainsString('0/100 [💧---------------------------]   0%', $output->fetch());

    // Need to sleep to prevent the default redraw frequency from skipping display.
    sleep(1);
    PullCommandBase::displayDownloadProgress(100, 50, $progress, $output);
    $this->assertStringContainsString('50/100 [==============💧-------------]  50%', $output->fetch());

    PullCommandBase::displayDownloadProgress(100, 100, $progress, $output);
    $this->assertStringContainsString('100/100 [============================] 100%', $output->fetch());
  }

  protected function mockDownloadBackup(object $database, object $environment, object $backup, int $curlCode = 0): object {
    if ($curlCode) {
      $this->prophet->prophesize(StreamInterface::class);
      /** @var RequestException|ObjectProphecy $requestException */
      $requestException = $this->prophet->prophesize(RequestException::class);
      $requestException->getHandlerContext()->willReturn(['errno' => $curlCode]);
      $this->clientProphecy->stream('get', "/environments/{$environment->id}/databases/{$database->name}/backups/1/actions/download", [])
        ->willThrow($requestException->reveal())
        ->shouldBeCalled();
      $response = $this->prophet->prophesize(ResponseInterface::class);
      $this->httpClientProphecy->request('GET', 'https://other.example.com/download-backup', Argument::type('array'))->willReturn($response->reveal())->shouldBeCalled();
      $domainsResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/domains', 'get', 200);
      $this->clientProphecy->request('get', "/environments/{$environment->id}/domains")->willReturn($domainsResponse->_embedded->items);
      $this->command->setBackupDownloadUrl(new Uri( 'https://www.example.com/download-backup'));
    }
    else {
      $this->mockDownloadBackupResponse($environment, $database->name, 1);
    }
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
        $backup->completedAt,
      ]) . '.sql.gz';
    $localFilepath = Path::join(sys_get_temp_dir(), $filename);
    $this->clientProphecy->addOption('sink', $localFilepath)->shouldBeCalled();
    $this->clientProphecy->addOption('curl.options', [
      'CURLOPT_FILE' => $localFilepath,
      'CURLOPT_RETURNTRANSFER' => FALSE,
])->shouldBeCalled();
    $this->clientProphecy->addOption('progress', Argument::type('Closure'))->shouldBeCalled();
    $this->clientProphecy->addOption('on_stats', Argument::type('Closure'))->shouldBeCalled();
    $this->clientProphecy->getOptions()->willReturn([]);

    return $database;
  }

}
