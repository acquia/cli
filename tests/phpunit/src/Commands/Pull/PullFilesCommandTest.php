<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullFilesCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use GuzzleHttp\Client;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class PullFilesCommandTest extends PullCommandTestBase {

  protected function createCommand(): CommandBase {
    $this->httpClientProphecy = $this->prophet->prophesize(Client::class);

    return new PullFilesCommand(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->acliRepoRoot,
      $this->clientServiceProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClientProphecy->reveal()
    );
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

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Run this command from the ');
    $this->executeCommand();
    IdeHelper::unsetCloudIdeEnvVars();
  }

  protected function mockExecuteRsync(
    LocalMachineHelper|ObjectProphecy $localMachineHelper,
                   mixed $environment,
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
