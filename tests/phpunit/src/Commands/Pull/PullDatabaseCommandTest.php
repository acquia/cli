<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\Pull\PullCodeCommand;
use Acquia\Cli\Command\Pull\PullCommand;
use Acquia\Cli\Command\Pull\PullDatabaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestBase;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

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

  public function testRefreshDatabases(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockAcsfEnvironmentsRequest($applications_response);
    $this->createMockGitConfigFile();
    $this->mockDatabasesResponse($environments_response);
    $ssh_helper = $this->prophet->prophesize(SshHelper::class);
    $this->mockGetAcsfSites($ssh_helper);
    $process = $this->mockProcess();

    $local_machine_helper = $this->mockLocalMachineHelper();
    // Set up file system.
    $local_machine_helper
      ->getFilesystem()
      ->willReturn($this->fs)
      ->shouldBeCalled();

    // Database.
    $mysql_dump_successful = TRUE;
    $this->mockExecuteSshMySqlDump($ssh_helper, $environments_response, $mysql_dump_successful);
    $this->mockDownloadMySqlDump($local_machine_helper);
    $this->mockExecuteMySqlDropDb($local_machine_helper, $process);
    $this->mockExecuteMySqlCreateDb($local_machine_helper, $process);
    $this->mockExecuteMySqlImport($local_machine_helper, $process);
    $this->mockExecuteSshRemove($ssh_helper, $environments_response, TRUE);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = $ssh_helper->reveal();

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

    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment:', $output);
    $this->assertStringContainsString('[0] Dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a database:', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
    $this->assertStringContainsString('profserv2 (default)', $output);
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteMySqlDropDb(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
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
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteMySqlCreateDb(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
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
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteMySqlImport(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    // MySQL import command.
    $local_machine_helper
      ->executeFromCmd(Argument::type('string'), Argument::type('callable'),
        NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy|SshHelper $ssh_helper
   * @param object $environments_response
   * @param bool $success
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function mockExecuteSshMySqlDump(
    ObjectProphecy $ssh_helper,
    $environments_response,
    $success
  ): void {
    $process = $this->mockProcess($success);
    $process->getOutput()->willReturn('dbdumpcontents');
    $ssh_helper->executeCommand(
      new EnvironmentResponse($environments_response),
      ['MYSQL_PWD=password mysqldump --host=fsdb-74.enterprise-g1.hosting.acquia.com --user=s164 profserv201dev | pv --rate --bytes | gzip -9 > /mnt/tmp/profserv201dev/acli-mysql-dump-dev-profserv201dev.sql.gz'],
      TRUE,
      NULL
    )
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   */
  protected function mockDownloadMySqlDump(ObjectProphecy $local_machine_helper): void {
    $process = $this->mockProcess(TRUE);
    $local_machine_helper->execute(
      Argument::containing('site.dev@server-123.hosted.hosting.acquia.com:/mnt/tmp/profserv201dev/acli-mysql-dump-dev-profserv201dev.sql.gz'),
      Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy|SshHelper $ssh_helper
   * @param object $environments_response
   * @param bool $success
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function mockExecuteSshRemove(
    ObjectProphecy $ssh_helper,
    $environments_response,
    $success
  ): void {
    $process = $this->mockProcess($success);
    $ssh_helper->executeCommand(
      new EnvironmentResponse($environments_response),
      Argument::containing('rm'),
      TRUE,
      NULL
    )
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
