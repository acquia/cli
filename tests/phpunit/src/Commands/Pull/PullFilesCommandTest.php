<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullFilesCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;

class PullFilesCommandTest extends PullCommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(PullFilesCommand::class);
  }

  public function testRefreshAcsfFiles(): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockAcsfEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $sshHelper = $this->mockSshHelper();
    $this->mockGetAcsfSites($sshHelper);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockGetFilesystem($localMachineHelper);
    $this->mockExecuteRsync($localMachineHelper, $selectedEnvironment, '/mnt/files/profserv2.dev/sites/g/files/jxr5000596dev/files/', $this->projectDir . '/docroot/sites/jxr5000596dev/files');

    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->command->sshHelper = $sshHelper->reveal();

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

  public function testRefreshCloudFiles(): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $sshHelper = $this->mockSshHelper();
    $this->mockGetCloudSites($sshHelper, $selectedEnvironment);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockGetFilesystem($localMachineHelper);
    $sitegroup = CommandBase::getSiteGroupFromSshUrl($selectedEnvironment->ssh_url);
    $this->mockExecuteRsync($localMachineHelper, $selectedEnvironment, '/mnt/files/' . $sitegroup . '.' . $selectedEnvironment->name . '/sites/bar/files/', $this->projectDir . '/docroot/sites/bar/files');

    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->command->sshHelper = $sshHelper->reveal();

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

  public function testInvalidCwd(): void {
    IdeHelper::setCloudIdeEnvVars();
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockDrupalSettingsRefresh($localMachineHelper);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Run this command from the ');
    $this->executeCommand();
    IdeHelper::unsetCloudIdeEnvVars();
  }

  /**
   * @param $environment
   */
  protected function mockExecuteRsync(
    LocalMachineHelper|ObjectProphecy $localMachineHelper,
                   $environment,
    string $sourceDir,
    string $destinationDir
  ): void {
    $process = $this->mockProcess();
    $localMachineHelper->checkRequiredBinariesExist(['rsync'])->shouldBeCalled();
    $command = [
      'rsync',
      '-avPhze',
      'ssh -o StrictHostKeyChecking=no',
      $environment->ssh_url . ':' . $sourceDir,
      $destinationDir,
    ];
    $localMachineHelper->execute($command, Argument::type('callable'), NULL, TRUE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
