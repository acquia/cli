<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Command\Pull\PullDatabaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshHelper;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class PullDatabaseCommandTest.
 *
 * @property \Acquia\Cli\Command\Pull\PullDatabaseCommand $command
 * @package Acquia\Cli\Tests\Commands\Pull
 */
class PullDatabaseCommandTest extends PullCommandTestBase {

  /**
   * {@inheritdoc}
   */
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

    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment:', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a database', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
    $this->assertStringContainsString('profserv2 (default)', $output);
  }

  public function testPullDatabasesOnDemand(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, TRUE, TRUE, TRUE);
    $inputs = $this->getInputs();

    $this->executeCommand([
      '--no-scripts' => TRUE,
      '--on-demand' => TRUE
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment:', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a database', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
    $this->assertStringContainsString('profserv2 (default)', $output);
  }

  /**
   * Test that settings files are created for multisite DBs in IDEs.
   *
   * @throws \Exception
   */
  public function testPullDatabaseSettingsFiles(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, TRUE, TRUE);
    $inputs = $this->getInputs();
    // @todo Use the IdeRequiredTestBase instead of setting AH_SITE_ENVIRONMENT.
    // IdeRequiredTestBase sets other env vars (such as application ID) that
    // seem to conflict with the rest of this test.
    putenv('AH_SITE_ENVIRONMENT=IDE');
    $this->executeCommand(['--no-scripts' => TRUE], $inputs);
    putenv('AH_SITE_ENVIRONMENT');
  }

  public function testPullDatabaseWithMySqlDownloadError(): void {
    $this->setupPullDatabase(FALSE, TRUE, TRUE, TRUE);
    $inputs = $this->getInputs();

    try {
      $this->executeCommand(['--no-scripts' => TRUE], $inputs);
    } catch (AcquiaCliException $exception) {
      $this->assertStringContainsString('Could not download remote database dump', $exception->getMessage());
    }
  }

  public function testPullDatabaseWithMySqlDropError(): void {
    $this->setupPullDatabase( TRUE, FALSE, TRUE, TRUE);
    $inputs = $this->getInputs();

    try {
      $this->executeCommand(['--no-scripts' => TRUE], $inputs);
    } catch (AcquiaCliException $exception) {
      $this->assertStringContainsString('Unable to drop a local database', $exception->getMessage());
    }
  }

  public function testPullDatabaseWithMySqlCreateError(): void {
    $this->setupPullDatabase(TRUE, TRUE, FALSE, TRUE);
    $inputs = $this->getInputs();

    try {
      $this->executeCommand(['--no-scripts' => TRUE], $inputs);
    } catch (AcquiaCliException $exception) {
      $this->assertStringContainsString('Unable to create a local database', $exception->getMessage());
    }
  }

  public function testPullDatabaseWithMySqlImportError(): void {
    $this->setupPullDatabase(TRUE, TRUE, TRUE, FALSE);
    $inputs = $this->getInputs();

    try {
      $this->executeCommand(['--no-scripts' => TRUE], $inputs);
    } catch (AcquiaCliException $exception) {
      $this->assertStringContainsString('Unable to import local database', $exception->getMessage());
    }
  }

  protected function setupPullDatabase($mysql_dl_successful, $mysql_drop_successful, $mysql_create_successful, $mysql_import_successful, $mock_ide_fs = FALSE, $on_demand = FALSE): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockAcsfEnvironmentsRequest($applications_response);
    $this->createMockGitConfigFile();
    $this->mockDatabasesResponse($environments_response);
    $this->mockDatabaseBackupsResponse($environments_response, 'profserv2');
    $this->mockDownloadBackupResponse($environments_response, 'profserv2', 1);
    $ssh_helper = $this->mockSshHelper();
    $this->mockGetAcsfSites($ssh_helper);

    if ($on_demand) {
      $this->mockDatabaseBackupCreateResponse($environments_response, 'profserv2');
      // Cloud API does not provide the notification UUID as part of the backup response, so we must hardcode it.
      $this->mockNotificationResponse('42b56cff-0b55-4bdf-a949-1fd0fca61c6c');
    }

    $fs = $this->prophet->prophesize(Filesystem::class);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockExecuteDrushExists($local_machine_helper);
    $this->mockExecuteDrushStatus($local_machine_helper, TRUE);
    // Set up file system.
    $local_machine_helper->getFilesystem()->willReturn($fs)->shouldBeCalled();

    // Mock IDE filesystem.
    if ($mock_ide_fs) {
      $this->mockDrupalSettingsRefresh($local_machine_helper);
      $this->mockSettingsFiles($fs);
    }

    // Database.
    $this->mockDownloadMySqlDump($local_machine_helper, $mysql_dl_successful);
    $this->mockExecuteMySqlDropDb($local_machine_helper, $mysql_drop_successful);
    $this->mockExecuteMySqlCreateDb($local_machine_helper, $mysql_create_successful);
    $this->mockExecuteMySqlImport($local_machine_helper, $mysql_import_successful);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = $ssh_helper->reveal();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $success
   */
  protected function mockExecuteMySqlDropDb(
    ObjectProphecy $local_machine_helper,
    $success
  ): void {
    $process = $this->mockProcess($success);
    $local_machine_helper
      ->execute([
        'mysql',
        '--host',
        'localhost',
        '--user',
        'drupal',
        '--password=drupal',
        '-e',
        'DROP DATABASE IF EXISTS drupal',
      ], Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $success
   */
  protected function mockExecuteMySqlCreateDb(
    ObjectProphecy $local_machine_helper,
    $success
  ): void {
    $process = $this->mockProcess($success);
    $local_machine_helper
      ->execute([
        'mysql',
        '--host',
        'localhost',
        '--user',
        'drupal',
        '--password=drupal',
        '-e',
        'create database drupal',
      ], Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $success
   */
  protected function mockExecuteMySqlImport(
    ObjectProphecy $local_machine_helper,
    $success
  ): void {
    $process = $this->mockProcess($success);
    // MySQL import command.
    $local_machine_helper
      ->executeFromCmd(Argument::type('string'), Argument::type('callable'),
        NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   */
  protected function mockDownloadMySqlDump(ObjectProphecy $local_machine_helper, $success): void {
    $process = $this->mockProcess($success);
    $local_machine_helper->writeFile(
      Argument::containingString("backup-something-profserv2.sql.gz"),
      Argument::type(StreamInterface::class)
    )
      ->shouldBeCalled();
  }

  /**
   * @return array
   */
  protected function getInputs(): array {
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];
    return $inputs;
  }

  protected function mockSettingsFiles($fs): void {
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

}
