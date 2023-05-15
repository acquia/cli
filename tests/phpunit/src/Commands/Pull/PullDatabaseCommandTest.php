<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Command\Pull\PullDatabaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Misc\LandoInfoHelper;
use AcquiaCloudApi\Response\DatabaseResponse;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @property \Acquia\Cli\Command\Pull\PullDatabaseCommand $command
 */
class PullDatabaseCommandTest extends PullCommandTestBase {

  protected string $dbUser = 'drupal';
  protected string $dbPassword = 'drupal';
  protected string $dbHost = 'localhost';
  protected string $dbName = 'drupal';

  public function providerTestPullDatabaseWithInvalidSslCertificate(): array {
    return [[51], [60]];
  }

  protected function createCommand(): Command {
    return $this->injectCommand(PullDatabaseCommand::class);
  }

  public function testPullDatabases(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, TRUE, TRUE);
    $inputs = $this->getInputs();

    $this->executeCommand([
    '--no-scripts' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a site [jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)]:', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
    $this->assertStringContainsString('Downloading backup 1', $output);
  }

  public function testPullDatabasesLocalConnectionFailure(): void {
    $this->setupPullDatabase(FALSE, TRUE, TRUE, TRUE, TRUE);
    $inputs = $this->getInputs();

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Unable to connect');
    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], $inputs);
  }

  public function testPullDatabasesIntoLando(): void {
    $landoInfo = LandoInfoHelper::getLandoInfo();
    LandoInfoHelper::setLandoInfo($landoInfo);
    $this->dbUser = 'root';
    $this->dbPassword = '';
    $this->dbHost = $landoInfo->database->hostnames[0];
    $this->setupPullDatabase(TRUE, TRUE, TRUE, TRUE, TRUE);
    $inputs = $this->getInputs();
    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], $inputs);
    LandoInfoHelper::unsetLandoInfo();
    $output = $this->getDisplay();
    self::assertStringContainsString('Acquia CLI assumes that the local name', $output);
  }

  public function testPullMultipleDatabases(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, TRUE, TRUE, FALSE, TRUE, TRUE);
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
    $this->prophet->checkPredictions();
  }

  public function testPullDatabasesOnDemand(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, TRUE, TRUE, TRUE);
    $inputs = $this->getInputs();

    $this->executeCommand([
      '--no-scripts' => TRUE,
      '--on-demand' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a site [jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)]:', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
  }

  public function testPullDatabasesSiteArgument(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, TRUE, TRUE, FALSE, FALSE);
    $inputs = $this->getInputs();

    $this->executeCommand([
      '--no-scripts' => TRUE,
      'site' => 'jxr5000596dev',
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringNotContainsString('Choose a database', $output);
  }

  public function testPullDatabaseWithMySqlDropError(): void {
    $this->setupPullDatabase(TRUE, FALSE, TRUE, TRUE);
    $inputs = $this->getInputs();
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Unable to drop a local database');
    $this->executeCommand(['--no-scripts' => TRUE], $inputs);
  }

  public function testPullDatabaseWithMySqlCreateError(): void {
    $this->setupPullDatabase(TRUE, TRUE, FALSE, TRUE);
    $inputs = $this->getInputs();

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Unable to create a local database');
    $this->executeCommand(['--no-scripts' => TRUE], $inputs);
  }

  public function testPullDatabaseWithMySqlImportError(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, FALSE);
    $inputs = $this->getInputs();

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Unable to import local database');
    $this->executeCommand(['--no-scripts' => TRUE], $inputs);
  }

  /**
   * @dataProvider providerTestPullDatabaseWithInvalidSslCertificate
   */
  public function testPullDatabaseWithInvalidSslCertificate($errorCode): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, TRUE, FALSE, FALSE, TRUE, FALSE, $errorCode);
    $inputs = $this->getInputs();

    $this->executeCommand(['--no-scripts' => TRUE], $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString('The certificate for www.example.com is invalid.', $output);
    $this->assertStringContainsString('Trying alternative host other.example.com', $output);
  }

  /**
   * @param $mysqlConnectSuccessful *
   */
  protected function setupPullDatabase($mysqlConnectSuccessful, $mysqlDropSuccessful, $mysqlCreateSuccessful, $mysqlImportSuccessful, $mockIdeFs = FALSE, $onDemand = FALSE, $mockGetAcsfSites = TRUE, $multidb = FALSE, int $curlCode = 0): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockAcsfEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $this->createMockGitConfigFile();

    $databasesResponse = $this->mockAcsfDatabasesResponse($selectedEnvironment);
    $databaseResponse = $databasesResponse[array_search('jxr5000596dev', array_column($databasesResponse, 'name'), TRUE)];
    $selectedDatabase = $this->mockDownloadBackup($databaseResponse, $selectedEnvironment, $curlCode);

    if ($multidb) {
      $databaseResponse2 = $databasesResponse[array_search('profserv2', array_column($databasesResponse, 'name'), TRUE)];
      $this->mockDownloadBackup($databaseResponse2, $selectedEnvironment, $curlCode);
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
      $this->mockSettingsFiles($fs);
    }

    // Database.
    $this->mockExecuteMySqlDropDb($localMachineHelper, $mysqlDropSuccessful);
    $this->mockExecuteMySqlCreateDb($localMachineHelper, $mysqlCreateSuccessful);
    $this->mockExecuteMySqlImport($localMachineHelper, $mysqlImportSuccessful);

    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->command->sshHelper = $sshHelper->reveal();
  }

  /**
   * @param ObjectProphecy|\Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   */
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

  protected function mockExecuteMySqlDropDb(
    \Acquia\Cli\Helpers\LocalMachineHelper|ObjectProphecy $localMachineHelper,
    bool $success
  ): void {
    $localMachineHelper->checkRequiredBinariesExist(["mysql"])->shouldBeCalled();
    $process = $this->mockProcess($success);
    $localMachineHelper
      ->execute(Argument::type('array'), Argument::type('callable'), NULL, FALSE, NULL, ['MYSQL_PWD' => $this->dbPassword])
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param ObjectProphecy|\Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   */
  protected function mockExecuteMySqlCreateDb(
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
        $this->dbUser,
        '-e',
        //'create database drupal',
        'create database jxr5000596dev',
      ], Argument::type('callable'), NULL, FALSE, NULL, ['MYSQL_PWD' => 'drupal'])
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param ObjectProphecy|\Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   */
  protected function mockExecuteMySqlImport(
    ObjectProphecy $localMachineHelper,
    bool $success
  ): void {
    $localMachineHelper->checkRequiredBinariesExist(['gunzip', 'mysql'])->shouldBeCalled();
    $this->mockExecutePvExists($localMachineHelper);
    $process = $this->mockProcess($success);
    // MySQL import command.
    $localMachineHelper
      ->executeFromCmd(Argument::type('string'), Argument::type('callable'),
        NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param ObjectProphecy|\Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   */
  protected function mockDownloadMySqlDump(ObjectProphecy $localMachineHelper, $success): void {
    $process = $this->mockProcess($success);
    $localMachineHelper->writeFile(
      Argument::containingString("dev-profserv2-profserv201dev-something.sql.gz"),
      'backupfilecontents'
    )
      ->shouldBeCalled();
  }

  /**
   * @return array
   */
  protected function getInputs(): array {
    return [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Choose an Acquia environment:
      0,
    ];
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

  protected function mockDownloadBackup(DatabaseResponse $selectedDatabase, object $selectedEnvironment, int $curlCode = 0): DatabaseResponse {
    $databaseBackupsResponse = $this->mockDatabaseBackupsResponse($selectedEnvironment, $selectedDatabase->name, 1);
    $selectedBackup = $databaseBackupsResponse->_embedded->items[0];
    if ($curlCode) {
      $this->prophet->prophesize(StreamInterface::class);
      /** @var RequestException|ObjectProphecy $requestException */
      $requestException = $this->prophet->prophesize(RequestException::class);
      $requestException->getHandlerContext()->willReturn(['errno' => $curlCode]);
      $this->clientProphecy->stream('get', "/environments/{$selectedEnvironment->id}/databases/{$selectedDatabase->name}/backups/1/actions/download", [])
        ->willThrow($requestException->reveal())
        ->shouldBeCalled();
      $response = $this->prophet->prophesize(ResponseInterface::class);
      $this->httpClientProphecy->request('GET', 'https://other.example.com/download-backup', Argument::type('array'))->willReturn($response->reveal())->shouldBeCalled();
      $domainsResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/domains', 'get', 200);
      $this->clientProphecy->request('get', "/environments/{$selectedEnvironment->id}/domains")->willReturn($domainsResponse->_embedded->items);
      $this->command->setBackupDownloadUrl(new Uri( 'https://www.example.com/download-backup'));
    }
    else {
      $this->mockDownloadBackupResponse($selectedEnvironment, $selectedDatabase->name, 1);
    }
    $localFilepath = PullCommandBase::getBackupPath($selectedEnvironment, $selectedDatabase, $selectedBackup);
    $this->clientProphecy->addOption('sink', $localFilepath)->shouldBeCalled();
    $this->clientProphecy->addOption('curl.options', [
      'CURLOPT_FILE' => $localFilepath,
      'CURLOPT_RETURNTRANSFER' => FALSE,
])->shouldBeCalled();
    $this->clientProphecy->addOption('progress', Argument::type('Closure'))->shouldBeCalled();
    $this->clientProphecy->addOption('on_stats', Argument::type('Closure'))->shouldBeCalled();
    $this->clientProphecy->getOptions()->willReturn([]);

    return $selectedDatabase;
  }

}
