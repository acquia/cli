<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\NewCommand;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class NewCommandTest.
 *
 * @property \Acquia\Cli\Command\NewCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class NewCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new NewCommand();
  }

  /**
   * Tests the 'refresh' command.
   */
  public function testRefreshCommand(): void {
    $this->setCommand($this->createCommand());

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);

    $local_machine_helper = $this->prophet->prophesize(LocalMachineHelper::class);
    $local_machine_helper->useTty()->willReturn(FALSE);
    $project_dir =  Path::join($this->projectFixtureDir, 'drupal');

    $this->mockExecuteComposerCreate($project_dir, $local_machine_helper, $process, 'acquia/blt-project');
    $this->mockExecuteComposerUpdate($local_machine_helper, $project_dir, $process);
    $this->mockExecuteGitInit($local_machine_helper, $project_dir, $process);
    $this->mockExecuteGitAdd($local_machine_helper, $project_dir, $process);
    $this->mockExecuteGitCommit($local_machine_helper, $project_dir, $process);

    $this->application->setLocalMachineHelper($local_machine_helper->reveal());
    $inputs = [
      // Which starting project would you like to use?
      'acquia/blt-project',
    ];
    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Which starting project would you like to use?', $output);
    $this->assertStringContainsString('[0] acquia/blt-project', $output);
    $this->assertStringContainsString('New 💧Drupal project created in ' . $project_dir, $output);
  }

  /**
   * @param string $project_dir
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   * @param string $project
   *
   * @return array
   */
  protected function mockExecuteComposerCreate(
    string $project_dir,
    \Prophecy\Prophecy\ObjectProphecy $local_machine_helper,
    \Prophecy\Prophecy\ObjectProphecy $process,
    $project
  ): array {
    $command = [
      'composer',
      'create-project',
      '--no-install',
      $project,
      $project_dir,
    ];
    $local_machine_helper
      ->execute($command)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param string $project_dir
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   *
   * @return array
   */
  protected function mockExecuteComposerUpdate(
    \Prophecy\Prophecy\ObjectProphecy $local_machine_helper,
    string $project_dir,
    \Prophecy\Prophecy\ObjectProphecy $process
  ): array {
    $command = [
      'composer',
      'update',
    ];
    $local_machine_helper
      ->execute($command, NULL, $project_dir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param string $project_dir
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   *
   * @return array
   */
  protected function mockExecuteGitInit(
    \Prophecy\Prophecy\ObjectProphecy $local_machine_helper,
    string $project_dir,
    \Prophecy\Prophecy\ObjectProphecy $process
  ): array {
    $command = [
      'git',
      'init',
    ];
    $local_machine_helper
      ->execute($command, NULL, $project_dir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param string $project_dir
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   *
   * @return array
   */
  protected function mockExecuteGitAdd(
    \Prophecy\Prophecy\ObjectProphecy $local_machine_helper,
    string $project_dir,
    \Prophecy\Prophecy\ObjectProphecy $process
  ): array {
    $command = [
      'git',
      'add',
      '-A',
    ];
    $local_machine_helper
      ->execute($command, NULL, $project_dir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param string $project_dir
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteGitCommit(
    \Prophecy\Prophecy\ObjectProphecy $local_machine_helper,
    string $project_dir,
    \Prophecy\Prophecy\ObjectProphecy $process
  ): void {
    $command = [
      'git',
      'commit',
      '--message',
      'Initial commit.',
      '--quiet',
    ];
    $local_machine_helper
      ->execute($command, NULL, $project_dir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
