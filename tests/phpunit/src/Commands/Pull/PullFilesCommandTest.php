<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\Pull\PullCodeCommand;
use Acquia\Cli\Command\Pull\PullCommand;
use Acquia\Cli\Command\Pull\PullDatabaseCommand;
use Acquia\Cli\Command\Pull\PullFilesCommand;
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
    $ssh_helper = $this->prophet->prophesize(SshHelper::class);
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
    $this->assertStringContainsString('Choose a Cloud Platform environment:', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  public function testRefreshCloudFiles(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockCloudEnvironmentsRequest($applications_response);
    $ssh_helper = $this->prophet->prophesize(SshHelper::class);
    $this->mockGetCloudSites($ssh_helper);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper
      ->getFilesystem()
      ->willReturn($this->fs)
      ->shouldBeCalled();
    $process = $this->mockProcess();
    $this->mockExecuteCloudRsync($local_machine_helper, $environments_response, $process);

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
    $this->assertStringContainsString('Choose a Cloud Platform environment:', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  public function testInvalidCwd(): void {
    IdeRequiredTestBase::setCloudIdeEnvVars();
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
    $command = [
      'rsync',
      '-rltDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com:/mnt/files/profserv2.dev/sites/g/files/jxr5000596dev/files',
      $this->projectFixtureDir . '/docroot/sites/default/',
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
    $environments_response,
    ObjectProphecy $process
  ): void {
    $command = [
      'rsync',
      '-rltDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      'something.dev@somethingdev.ssh.prod.acquia-sites.com:/mnt/files/something.dev/sites/default/files',
      $this->projectFixtureDir . '/docroot/sites/default/',
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, FALSE, 60 * 60)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
