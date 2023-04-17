<?php

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\CommandBase;
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

  protected function createCommand(): Command {
    return $this->injectCommand(PushFilesCommand::class);
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \JsonException
   */
  public function testPushFilesAcsf(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockAcsfEnvironmentsRequest($applications_response);
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
      // Select a Cloud Platform application:
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

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  public function testPushFilesCloud(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $ssh_helper = $this->mockSshHelper();
    $this->mockGetCloudSites($ssh_helper, $selected_environment);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $this->mockExecuteCloudRsync($local_machine_helper, $process, $selected_environment);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = $ssh_helper->reveal();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
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

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  protected function mockExecuteCloudRsync(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process,
    $environment
  ): void {
    $local_machine_helper->checkRequiredBinariesExist(['rsync'])->shouldBeCalled();
    $sitegroup = CommandBase::getSiteGroupFromSshUrl($environment->ssh_url);
    $command = [
      'rsync',
      '-avPhze',
      'ssh -o StrictHostKeyChecking=no',
      $this->projectDir . '/docroot/sites/default/files/',
      $environment->ssh_url . ':/mnt/files/' . $sitegroup . '.' . $environment->name . '/sites/bar/files',
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteAcsfRsync(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $local_machine_helper->checkRequiredBinariesExist(['rsync'])->shouldBeCalled();
    $command = [
      'rsync',
      '-avPhze',
      'ssh -o StrictHostKeyChecking=no',
      $this->projectDir . '/docroot/sites/default/files/',
      'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com:/mnt/files/profserv2.dev/sites/g/files/jxr5000596dev/files',
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
