<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\NewCommand;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

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
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testRefreshCommand(): void {
    $this->setCommand($this->createCommand());

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);

    $local_machine_helper = $this->prophet->prophesize(LocalMachineHelper::class);
    $local_machine_helper->useTty()->willReturn(FALSE);
    $project_dir =  $this->projectFixtureDir . '/drupal';

    $command = [
      'composer',
      'create-project',
      '--no-install',
      'acquia/blt-project',
      $project_dir,
    ];
    $local_machine_helper
      ->execute($command)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $command = [
      'composer',
      'update',
    ];
    $local_machine_helper
      ->execute($command, NULL, $project_dir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $command = [
      'git',
      'init',
    ];
    $local_machine_helper
      ->execute($command, NULL, $project_dir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $command = [
      'git',
      'add',
      '-A',
    ];
    $local_machine_helper
      ->execute($command, NULL, $project_dir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

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

}
