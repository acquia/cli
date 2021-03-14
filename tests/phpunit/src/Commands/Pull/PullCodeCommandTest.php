<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\Pull\PullCodeCommand;
use Acquia\Cli\Command\Pull\PullCommand;
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
 * Class PullCodeCommandTest.
 *
 * @property \Acquia\Cli\Command\Pull\PullCodeCommand $command
 * @package Acquia\Cli\Tests\Commands\Pull
 */
class PullCodeCommandTest extends PullCommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(PullCodeCommand::class);
  }

  public function testCloneRepo(): void {
    // Unset repo root. Mimics failing to find local git repo. Command must be re-created
    // to re-inject the parameter into the command.
    $this->acliRepoRoot = '';
    $this->command = $this->createCommand();
    // Client responses.
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $dir = Path::join($this->fixtureDir, 'empty-dir');
    $this->fs->mkdir([$dir]);
    $this->mockExecuteGitClone($local_machine_helper, $environments_response, $process, $dir);
    $this->mockExecuteGitCheckout($local_machine_helper, $environments_response->vcs->path, $dir, $process);
    $local_machine_helper->getFinder()->willReturn(new Finder());

    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $inputs = [
      // Would you like to clone a project into the current directory?
      'y',
      // Please select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];
    $this->executeCommand([
      '--no-scripts' => TRUE,
      '--dir' => $dir,
    ], $inputs);
    $this->prophet->checkPredictions();
  }

  public function testPullCode(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($local_machine_helper, $process, $this->projectFixtureDir, $environments_response->vcs->path);
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $this->projectFixtureDir);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];

    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  public function testPullCodeDirtyRepo(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($local_machine_helper, $process, $this->projectFixtureDir, $environments_response->vcs->path);
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $this->projectFixtureDir);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];

    try {
    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], $inputs);
    } catch (AcquiaCliException $exception) {
      $this->assertStringContainsString('Pulling code from your Cloud Platform environment was aborted because your local Git repository has uncommitted changes', $exception->getMessage());
    }
  }

  public function testPullCodeGitStatusFail(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($local_machine_helper, $process, $this->projectFixtureDir, $environments_response->vcs->path);
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $this->projectFixtureDir);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];

    try {
      $this->executeCommand([
        '--no-scripts' => TRUE,
      ], $inputs);
    } catch (AcquiaCliException $exception) {
      $this->assertStringContainsString('unable to determine', $exception->getMessage());
    }

  }

  public function testWithScripts(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($local_machine_helper, $process, $this->projectFixtureDir, $environments_response->vcs->path);
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $this->projectFixtureDir);
    $process = $this->mockProcess();
    $this->mockExecuteComposerExists($local_machine_helper);
    $this->mockExecuteComposerInstall($local_machine_helper, $process);
    $this->mockExecuteDrushExists($local_machine_helper);
    $this->mockExecuteDrushStatus($local_machine_helper, TRUE, $this->projectFixtureDir);
    $this->mockExecuteDrushCacheRebuild($local_machine_helper, $process);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
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

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testMatchPhpVersion(): void {
    IdeRequiredTestBase::setCloudIdeEnvVars();
    $this->application->addCommands([
      $this->injectCommand(IdePhpVersionCommand::class),
    ]);
    $this->command = $this->createCommand();
    $dir = '/home/ide/project';
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($local_machine_helper, $process, $dir, 'master');
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $dir);

    $environment_response = $this->getMockEnvironmentResponse();
    $environment_response->configuration->php->version = '7.1';
    $environment_response->sshUrl = $environment_response->ssh_url;
    $this->clientProphecy->request('get',
      "/environments/" . $environment_response->id)
      ->willReturn($environment_response)
      ->shouldBeCalled();

    $this->executeCommand([
      // @todo Execute ONLY match php aspect, not the code pull.
      'environmentId' => $environment_response->id,
      '--dir' => $dir,
      '--no-scripts' => TRUE,
    ], [
      // Please choose an Acquia environment:
      0,
      // Would you like to change the PHP version on this IDE to match the PHP version on ... ?
      'n',
    ]);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString("Would you like to change the PHP version on this IDE to match the PHP version on the {$environment_response->label} ({$environment_response->configuration->php->version}) environment?", $output);
    IdeRequiredTestBase::unsetCloudIdeEnvVars();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param object $environments_response
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   * @param $dir
   */
  protected function mockExecuteGitClone(
    ObjectProphecy $local_machine_helper,
    $environments_response,
    ObjectProphecy $process,
    $dir
  ): void {
    $command = [
      'GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=no"',
      'git',
      'clone',
      $environments_response->vcs->url,
      $dir,
    ];
    $command = implode(' ', $command);
    $local_machine_helper->executeFromCmd($command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   * @param $cwd
   * @param $vcs_path
   */
  protected function mockExecuteGitFetchAndCheckout(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process,
    $cwd,
    $vcs_path
  ): void {
    $local_machine_helper->execute([
      'git',
      'fetch',
      '--all',
    ], Argument::type('callable'), $cwd, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->mockExecuteGitCheckout($local_machine_helper, $vcs_path, $cwd, $process);
  }

  /**
   * @param $failed
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $cwd
   */
  protected function mockExecuteGitStatus(
    $failed,
    ObjectProphecy $local_machine_helper,
    $cwd
  ): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(!$failed)->shouldBeCalled();
    $process->getOutput()->willReturn('')->shouldBeCalled();
    $local_machine_helper->execute([
      'git',
      'status',
      '--short',
    ], NULL, $cwd, FALSE)->willReturn($process->reveal())->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $vcs_path
   * @param $cwd
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteGitCheckout(ObjectProphecy $local_machine_helper, $vcs_path, $cwd, ObjectProphecy $process): void {
    $local_machine_helper->execute([
      'git',
      'checkout',
      $vcs_path,
    ], Argument::type('callable'), $cwd, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
