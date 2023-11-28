<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\Push\PushDatabaseCommand;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Push\PushDatabaseCommand $command
 */
class PushDatabaseCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(PushDatabaseCommand::class);
  }

  public function setUp(): void {
    self::unsetEnvVars(['ACLI_DB_HOST', 'ACLI_DB_USER', 'ACLI_DB_PASSWORD', 'ACLI_DB_NAME']);
    parent::setUp();
  }

  public function testPushDatabase(): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockAcsfEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $this->createMockGitConfigFile();
    $this->mockAcsfDatabasesResponse($selectedEnvironment);
    $sshHelper = $this->mockSshHelper();
    $this->mockGetAcsfSites($sshHelper);
    $process = $this->mockProcess();

    $localMachineHelper = $this->mockLocalMachineHelper();

    // Database.
    $this->mockExecutePvExists($localMachineHelper);
    $this->mockCreateMySqlDumpOnLocal($localMachineHelper);
    $this->mockUploadDatabaseDump($localMachineHelper, $process);
    $this->mockImportDatabaseDumpOnRemote($sshHelper, $selectedEnvironment, $process);

    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->command->sshHelper = $sshHelper->reveal();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
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

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a database', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
    $this->assertStringContainsString('profserv2 (default)', $output);
    $this->assertStringContainsString('Overwrite the jxr136 database on dev with a copy of the database from the current machine?', $output);
  }

  protected function mockUploadDatabaseDump(
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process
  ): void {
    $localMachineHelper->checkRequiredBinariesExist(['rsync'])->shouldBeCalled();
    $command = [
      'rsync',
      '-tDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      sys_get_temp_dir() . '/acli-mysql-dump-drupal.sql.gz',
      'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com:/mnt/tmp/profserv2.dev/acli-mysql-dump-drupal.sql.gz',
    ];
    $localMachineHelper->execute($command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockImportDatabaseDumpOnRemote(
    ObjectProphecy $sshHelper,
    object $environmentsResponse,
    mixed $process
  ): void {
    $sshHelper->executeCommand(
      new EnvironmentResponse($environmentsResponse),
      ['pv /mnt/tmp/profserv2.dev/acli-mysql-dump-drupal.sql.gz --bytes --rate | gunzip | MYSQL_PWD=password mysql --host=fsdb-74.enterprise-g1.hosting.acquia.com.enterprise-g1.hosting.acquia.com --user=s164 profserv2db14390'],
      TRUE,
      NULL
    )
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteMySqlImport(
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process
  ): void {
    // MySQL import command.
    $localMachineHelper
      ->executeFromCmd(Argument::type('string'), Argument::type('callable'),
        NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
