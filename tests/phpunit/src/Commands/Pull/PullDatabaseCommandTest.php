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
    $lando_info = LandoInfoHelper::getLandoInfo();
    LandoInfoHelper::setLandoInfo($lando_info);
    $this->dbUser = 'root';
    $this->dbPassword = '';
    $this->dbHost = $lando_info->database->hostnames[0];
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
    $this->assertStringContainsString('The certificate for www.example.com is invalid, trying alternative host other.example.com', $output);
  }

  /**
   * @param $mysql_connect_successful *
   */
  protected function setupPullDatabase($mysql_connect_successful, $mysql_drop_successful, $mysql_create_successful, $mysql_import_successful, $mock_ide_fs = FALSE, $on_demand = FALSE, $mock_get_acsf_sites = TRUE, $multidb = FALSE, int $curl_code = 0): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockAcsfEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $this->createMockGitConfigFile();

    $databases_response = $this->mockAcsfDatabasesResponse($selected_environment);
    $database_response = $databases_response[array_search('jxr5000596dev', array_column($databases_response, 'name'), TRUE)];
    $selected_database = $this->mockDownloadBackup($database_response, $selected_environment, $curl_code);

    if ($multidb) {
      $database_response_2 = $databases_response[array_search('profserv2', array_column($databases_response, 'name'), TRUE)];
      $this->mockDownloadBackup($database_response_2, $selected_environment, $curl_code);
    }

    $ssh_helper = $this->mockSshHelper();
    if ($mock_get_acsf_sites) {
      $this->mockGetAcsfSites($ssh_helper);
    }

    if ($on_demand) {
      $this->mockDatabaseBackupCreateResponse($selected_environment, $selected_database->name);
      // Cloud API does not provide the notification UUID as part of the backup response, so we must hardcode it.
      $this->mockNotificationResponse('42b56cff-0b55-4bdf-a949-1fd0fca61c6c');
    }

    $fs = $this->prophet->prophesize(Filesystem::class);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockExecuteMySqlConnect($local_machine_helper, $mysql_connect_successful);
    // Set up file system.
    $local_machine_helper->getFilesystem()->willReturn($fs)->shouldBeCalled();

    // Mock IDE filesystem.
    if ($mock_ide_fs) {
      $this->mockDrupalSettingsRefresh($local_machine_helper);
      $this->mockSettingsFiles($fs);
    }

    // Database.
    $this->mockExecuteMySqlDropDb($local_machine_helper, $mysql_drop_successful);
    $this->mockExecuteMySqlCreateDb($local_machine_helper, $mysql_create_successful);
    $this->mockExecuteMySqlImport($local_machine_helper, $mysql_import_successful);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = $ssh_helper->reveal();
  }

  /**
   * @param ObjectProphecy|\Acquia\Cli\Helpers\LocalMachineHelper $local_machine_helper
   */
  protected function mockExecuteMySqlConnect(
    ObjectProphecy $local_machine_helper,
    bool $success
  ): void {
    $local_machine_helper->checkRequiredBinariesExist(["mysql"])->shouldBeCalled();
    $process = $this->mockProcess($success);
    $local_machine_helper
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
    \Acquia\Cli\Helpers\LocalMachineHelper|ObjectProphecy $local_machine_helper,
    bool $success
  ): void {
    $local_machine_helper->checkRequiredBinariesExist(["mysql"])->shouldBeCalled();
    $process = $this->mockProcess($success);
    $local_machine_helper
      ->execute(Argument::type('array'), Argument::type('callable'), NULL, FALSE, NULL, ['MYSQL_PWD' => $this->dbPassword])
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param ObjectProphecy|\Acquia\Cli\Helpers\LocalMachineHelper $local_machine_helper
   */
  protected function mockExecuteMySqlCreateDb(
    ObjectProphecy $local_machine_helper,
    bool $success
  ): void {
    $local_machine_helper->checkRequiredBinariesExist(["mysql"])->shouldBeCalled();
    $process = $this->mockProcess($success);
    $local_machine_helper
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
   * @param ObjectProphecy|\Acquia\Cli\Helpers\LocalMachineHelper $local_machine_helper
   */
  protected function mockExecuteMySqlImport(
    ObjectProphecy $local_machine_helper,
    bool $success
  ): void {
    $local_machine_helper->checkRequiredBinariesExist(['gunzip', 'mysql'])->shouldBeCalled();
    $this->mockExecutePvExists($local_machine_helper);
    $process = $this->mockProcess($success);
    // MySQL import command.
    $local_machine_helper
      ->executeFromCmd(Argument::type('string'), Argument::type('callable'),
        NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param ObjectProphecy|\Acquia\Cli\Helpers\LocalMachineHelper $local_machine_helper
   */
  protected function mockDownloadMySqlDump(ObjectProphecy $local_machine_helper, $success): void {
    $process = $this->mockProcess($success);
    $local_machine_helper->writeFile(
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

  protected function mockDownloadBackup(DatabaseResponse $selected_database, object $selected_environment, int $curl_code = 0): DatabaseResponse {
    $database_backups_response = $this->mockDatabaseBackupsResponse($selected_environment, $selected_database->name, 1);
    $selected_backup = $database_backups_response->_embedded->items[0];
    if ($curl_code) {
      $this->prophet->prophesize(StreamInterface::class);
      /** @var RequestException|ObjectProphecy $request_exception */
      $request_exception = $this->prophet->prophesize(RequestException::class);
      $request_exception->getHandlerContext()->willReturn(['errno' => $curl_code]);
      $this->clientProphecy->stream('get', "/environments/{$selected_environment->id}/databases/{$selected_database->name}/backups/1/actions/download", [])
        ->willThrow($request_exception->reveal())
        ->shouldBeCalled();
      $response = $this->prophet->prophesize(ResponseInterface::class);
      $this->httpClientProphecy->request('GET', 'https://other.example.com/download-backup', Argument::type('array'))->willReturn($response->reveal())->shouldBeCalled();
      $domains_response = $this->getMockResponseFromSpec('/environments/{environmentId}/domains', 'get', 200);
      $this->clientProphecy->request('get', "/environments/{$selected_environment->id}/domains")->willReturn($domains_response->_embedded->items);
      $this->command->setBackupDownloadUrl(new Uri( 'https://www.example.com/download-backup'));
    }
    else {
      $this->mockDownloadBackupResponse($selected_environment, $selected_database->name, 1);
    }
    $local_filepath = PullCommandBase::getBackupPath($selected_environment, $selected_database, $selected_backup);
    $this->clientProphecy->addOption('sink', $local_filepath)->shouldBeCalled();
    $this->clientProphecy->addOption('curl.options', [
      'CURLOPT_FILE' => $local_filepath,
      'CURLOPT_RETURNTRANSFER' => FALSE,
])->shouldBeCalled();
    $this->clientProphecy->addOption('progress', Argument::type('Closure'))->shouldBeCalled();
    $this->clientProphecy->addOption('on_stats', Argument::type('Closure'))->shouldBeCalled();
    $this->clientProphecy->getOptions()->willReturn([]);

    return $selected_database;
  }

}
