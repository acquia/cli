<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullFilesCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;

/**
 * Class PullDatabaseCommandTest.
 *
 * @property \Acquia\Cli\Command\Pull\PullFilesCommand $command
 * @package Acquia\Cli\Tests\Commands\Pull
 */
class PullFilesCommandTest extends PullCommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(PullFilesCommand::class);
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \JsonException
   */
  public function testRefreshAcsfFiles(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockAcsfEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $ssh_helper = $this->mockSshHelper();
    $this->mockGetAcsfSites($ssh_helper);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockGetFilesystem($local_machine_helper);
    $this->mockExecuteRsync($local_machine_helper, $selected_environment, '/mnt/files/profserv2.dev/sites/g/files/jxr5000596dev/files', $this->projectDir . '/docroot/sites/jxr5000596dev/');

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = $ssh_helper->reveal();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Choose an Acquia environment:
      0,
      // Choose site from which to copy files:
      0,
    ];

    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \JsonException
   */
  public function testRefreshCloudFiles(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $ssh_helper = $this->mockSshHelper();
    $this->mockGetCloudSites($ssh_helper, $selected_environment);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockGetFilesystem($local_machine_helper);
    $sitegroup = CommandBase::getSiteGroupFromSshUrl($selected_environment->ssh_url);
    $this->mockExecuteRsync($local_machine_helper, $selected_environment, '/mnt/files/' . $sitegroup . '.' . $selected_environment->name . '/sites/bar/files/', $this->projectDir . '/docroot/sites/bar/files');

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = $ssh_helper->reveal();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Choose an Acquia environment:
      0,
      // Choose site from which to copy files:
      0,
    ];

    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  /**
   * @throws \Exception
   */
  public function testInvalidCwd(): void {
    IdeHelper::setCloudIdeEnvVars();
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockDrupalSettingsRefresh($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Run this command from the ');
    $this->executeCommand();
    IdeHelper::unsetCloudIdeEnvVars();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $environment
   * @param string $source_dir
   * @param string $destination_dir
   */
  protected function mockExecuteRsync(
    ObjectProphecy $local_machine_helper,
                   $environment,
    string $source_dir,
    string $destination_dir
  ): void {
    $process = $this->mockProcess();
    $local_machine_helper->checkRequiredBinariesExist(['rsync'])->shouldBeCalled();
    $command = [
      'rsync',
      '-avPhze',
      'ssh -o StrictHostKeyChecking=no',
      $environment->ssh_url . ':' . $source_dir,
      $destination_dir
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, FALSE, 60 * 60)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
