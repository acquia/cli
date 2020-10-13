<?php

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\Pull\PullCodeCommand;
use Acquia\Cli\Command\Pull\PullCommand;
use Acquia\Cli\Command\Pull\PullDatabaseCommand;
use Acquia\Cli\Command\Pull\PullFilesCommand;
use Acquia\Cli\Command\Push\PushFilesCommand;
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

  public function testPushFiles(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockAcsfEnvironmentsRequest($applications_response);
    $ssh_helper = $this->prophet->prophesize(SshHelper::class);
    $this->mockGetAcsfSites($ssh_helper);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $this->mockExecuteRsync($local_machine_helper, $environments_response, $process);

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
    $this->assertStringContainsString('[0] Dev (vcs: master)', $output);
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param object $environments_response
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteRsync(
    ObjectProphecy $local_machine_helper,
    $environments_response,
    ObjectProphecy $process
  ): void {
    $command = [
      'rsync',
      '-rltDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      $this->projectFixtureDir . '/docroot/sites/default/',
      // $environments_response->ssh_url . ':/home/' . RefreshCommand::getSiteGroupFromSshUrl($environments_response) . '/' . $environments_response->name . '/sites/default/files',
      'site.dev@server-123.hosted.hosting.acquia.com:/mnt/files/site.dev/sites/g/files/jxr5000596dev/files',
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
