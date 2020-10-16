<?php

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\Push\PushDatabaseCommand;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;

/**
 * Class PullDatabaseCommandTest.
 *
 * @property \Acquia\Cli\Command\Push\PushDatabaseCommand $command
 * @package Acquia\Cli\Tests\Commands\Push
 */
class PushDatabaseCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(PushDatabaseCommand::class);
  }

  public function testPushDatabase(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockAcsfEnvironmentsRequest($applications_response);
    $this->createMockGitConfigFile();
    $this->mockDatabasesResponse($environments_response);
    $ssh_helper = $this->prophet->prophesize(SshHelper::class);
    $this->mockGetAcsfSites($ssh_helper);
    $process = $this->mockProcess();

    $local_machine_helper = $this->mockLocalMachineHelper();

    // Database.
    $this->mockCreateMySqlDumpOnLocal($local_machine_helper, $environments_response);
    $this->mockUploadDatabaseDump($local_machine_helper, $process);
    $this->mockImportDatabaseDumpOnRemote($ssh_helper, $environments_response, $process);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = $ssh_helper->reveal();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Choose a Cloud Platform environment
      0,
      // Choose a database
      0,
      // Overwrite the profserv2 database on dev with a copy of the database from the current machine?
      'y',
    ];

    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a database', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
    $this->assertStringContainsString('profserv2 (default)', $output);
    $this->assertStringContainsString('Overwrite the jxr136 database on dev with a copy of the database from the current machine?', $output);
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param object $environments_response
   */
  protected function mockCreateMySqlDumpOnLocal(
    ObjectProphecy $local_machine_helper,
    $environments_response
  ): void {
    $process = $this->mockProcess(TRUE);
    $local_machine_helper->executeFromCmd(
      'MYSQL_PWD=drupal mysqldump --host=localhost --user=drupal drupal | pv --rate --bytes | gzip -9 > /tmp/acli-mysql-dump-drupal.sql.gz',
      NULL, NULL, TRUE)->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockUploadDatabaseDump(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $command = [
      'rsync',
      '-tDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      '/tmp/acli-mysql-dump-drupal.sql.gz',
      'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com:/mnt/tmp/profserv2db14390/acli-mysql-dump-drupal.sql.gz',
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy|SshHelper $ssh_helper
   * @param object $environments_response
   * @param $process
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function mockImportDatabaseDumpOnRemote(
    ObjectProphecy $ssh_helper,
    $environments_response,
    $process
  ): void {
    $ssh_helper->executeCommand(
      new EnvironmentResponse($environments_response),
      ['pv /mnt/tmp/profserv2db14390/acli-mysql-dump-drupal.sql.gz --bytes --rate | gunzip | MYSQL_PWD=password mysql --host=fsdb-74.enterprise-g1.hosting.acquia.com.enterprise-g1.hosting.acquia.com --user=s164 profserv2db14390'],
      TRUE,
      NULL
    )
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

}
