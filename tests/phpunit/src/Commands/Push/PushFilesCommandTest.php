<?php

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\Push\PushFilesCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;

/**
 * Class PushFilesCommandTest.
 *
 * @property \Acquia\Cli\Command\Push\PushFilesCommand $command
 * @package Acquia\Cli\Tests\Commands\Push
 */
class PushFilesCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(PushFilesCommand::class);
  }

  public function testPushFilesAcsf(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockAcsfEnvironmentsRequest($applications_response);
    $ssh_helper = $this->mockSshHelper();
    $this->mockGetAcsfSites($ssh_helper);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $this->mockExecuteAcsfRsync($local_machine_helper, $process);

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
      // Choose a site
      0,
      // Overwrite the public files directory
      'y',
    ];

    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  public function testPushFilesCloud(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockCloudEnvironmentsRequest($applications_response);
    $ssh_helper = $this->mockSshHelper();
    $this->mockGetCloudSites($ssh_helper);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $this->mockExecuteCloudRsync($local_machine_helper, $process);

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
      // Choose a site
      0,
      // Overwrite the public files directory
      'y',
    ];

    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteCloudRsync(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $command = [
      'rsync',
      '-rltDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      $this->projectFixtureDir . '/docroot/sites/default/files/',
      'something.dev@somethingdev.ssh.prod.acquia-sites.com:/mnt/files/something.dev/sites/bar/files',
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteAcsfRsync(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $command = [
      'rsync',
      '-rltDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      $this->projectFixtureDir . '/docroot/sites/default/files/',
      'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com:/mnt/files/profserv2.dev/sites/g/files/jxr5000596dev/files',
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
