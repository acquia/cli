<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullFilesCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestBase;
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

  public function testRefreshAcsfFiles(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockAcsfEnvironmentsRequest($applications_response);
    $ssh_helper = $this->mockSshHelper();
    $this->mockGetAcsfSites($ssh_helper);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper
      ->getFilesystem()
      ->willReturn($this->fs)
      ->shouldBeCalled();
    $process = $this->mockProcess();
    $this->mockExecuteAcsfRsync($local_machine_helper, $environments_response, $process);

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
      // Choose site from which to copy files:
      0,
    ];

    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  public function testRefreshCloudFiles(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $ssh_helper = $this->mockSshHelper();
    $this->mockGetCloudSites($ssh_helper, $selected_environment);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper
      ->getFilesystem()
      ->willReturn($this->fs)
      ->shouldBeCalled();
    $process = $this->mockProcess();
    $this->mockExecuteCloudRsync($local_machine_helper, $process, $selected_environment);

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
      // Choose site from which to copy files:
      0,
    ];

    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select a Cloud Platform application', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  public function testInvalidCwd(): void {
    IdeRequiredTestBase::setCloudIdeEnvVars();
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockDrupalSettingsRefresh($local_machine_helper);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    try {
      $this->executeCommand([], []);
    } catch (AcquiaCliException $exception) {
      $this->assertStringContainsString('Please run this command from the ', $exception->getMessage());
    }
    IdeRequiredTestBase::unsetCloudIdeEnvVars();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param object $environments_response
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteAcsfRsync(
    ObjectProphecy $local_machine_helper,
    $environments_response,
    ObjectProphecy $process
  ): void {
    $this->localMachineHelper->checkRequiredBinariesExist(['rsync']);
    $command = [
      'rsync',
      '-rltDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com:/mnt/files/profserv2.dev/sites/g/files/jxr5000596dev/files',
      $this->projectFixtureDir . '/docroot/sites/jxr5000596dev/',
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, FALSE, 60 * 60)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param object $environments_response
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteCloudRsync(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process,
    $environment
  ): void {
    $sitegroup = CommandBase::getSiteGroupFromSshUrl($environment->ssh_url);
    $command = [
      'rsync',
      '-rltDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      $environment->ssh_url . ':/mnt/files/' . $sitegroup . '.' . $environment->name . '/sites/bar/files',
      $this->projectFixtureDir . '/docroot/sites/bar/',
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, FALSE, 60 * 60)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
