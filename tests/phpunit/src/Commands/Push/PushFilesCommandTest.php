<?php

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Push\PushFilesCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;

class PushFilesCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(PushFilesCommand::class);
  }

  public function testPushFilesAcsf(): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockAcsfEnvironmentsRequest($applicationsResponse);
    $sshHelper = $this->mockSshHelper();
    $multisiteConfig = $this->mockGetAcsfSites($sshHelper);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $this->mockExecuteAcsfRsync($localMachineHelper, $process, reset($multisiteConfig['sites'])['name']);

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
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $sshHelper = $this->mockSshHelper();
    $this->mockGetCloudSites($sshHelper, $selectedEnvironment);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $this->mockExecuteCloudRsync($localMachineHelper, $process, $selectedEnvironment);

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
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process,
    mixed $environment
  ): void {
    $localMachineHelper->checkRequiredBinariesExist(['rsync'])->shouldBeCalled();
    $sitegroup = CommandBase::getSiteGroupFromSshUrl($environment->ssh_url);
    $command = [
      'rsync',
      '-avPhze',
      'ssh -o StrictHostKeyChecking=no',
      $this->projectDir . '/docroot/sites/bar/files/',
      $environment->ssh_url . ':/mnt/files/' . $sitegroup . '.' . $environment->name . '/sites/bar/files',
    ];
    $localMachineHelper->execute($command, Argument::type('callable'), NULL, TRUE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteAcsfRsync(
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process,
    string $site
  ): void {
    $localMachineHelper->checkRequiredBinariesExist(['rsync'])->shouldBeCalled();
    $command = [
      'rsync',
      '-avPhze',
      'ssh -o StrictHostKeyChecking=no',
      $this->projectDir . '/docroot/sites/' . $site . '/files/',
      'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com:/mnt/files/profserv2.dev/sites/g/files/' . $site . '/files',
    ];
    $localMachineHelper->execute($command, Argument::type('callable'), NULL, TRUE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
