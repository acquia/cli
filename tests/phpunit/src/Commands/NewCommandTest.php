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

  protected $newProjectDir;

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->newProjectDir =  Path::join($this->projectFixtureDir, 'drupal');
    $this->fs->remove($this->newProjectDir);
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->fs->remove($this->newProjectDir);
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new NewCommand();
  }

  public function provideTestRefreshCommand() {
    return [
      ['acquia/blt-project'],
      ['drupal/recommended-project'],
    ];
  }

  /**
   * Tests the 'refresh' command.
   *
   * @dataProvider provideTestRefreshCommand
   */
  public function testRefreshCommand($project): void {
    $this->setCommand($this->createCommand());

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);

    $local_machine_helper = $this->prophet->prophesize(LocalMachineHelper::class);
    $local_machine_helper->useTty()->willReturn(FALSE);

    $this->mockExecuteComposerCreate($this->newProjectDir, $local_machine_helper, $process, $project);
    $this->fs->copy(Path::join($this->projectFixtureDir, 'composer.json'), Path::join($this->newProjectDir, 'composer.json'));
    $this->mockExecuteComposerUpdate($local_machine_helper, $this->newProjectDir, $process);
    $this->mockExecuteGitInit($local_machine_helper, $this->newProjectDir, $process);
    $this->mockExecuteGitAdd($local_machine_helper, $this->newProjectDir, $process);
    $this->mockExecuteGitCommit($local_machine_helper, $this->newProjectDir, $process);

    if ($project === 'drupal/recommended-project') {
      $local_machine_helper
        ->execute([
          'composer',
          'require',
          'drush/drush',
          '--no-update',
        ], NULL, $this->newProjectDir)
        ->willReturn($process->reveal())
        ->shouldBeCalled();
    }

    $this->application->setLocalMachineHelper($local_machine_helper->reveal());
    $inputs = [
      // Which starting project would you like to use?
      $project,
    ];
    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Which starting project would you like to use?', $output);
    $this->assertStringContainsString($project, $output);
    $this->assertStringContainsString('New 💧Drupal project created in ' . $this->newProjectDir, $output);

    if ($project === 'drupal/recommended-project') {
      $composer_json = file_get_contents(Path::join($this->newProjectDir, 'composer.json'));
      $this->assertStringNotContainsString('web/', $composer_json);
      $this->assertStringContainsString('docroot/', $composer_json);
    }
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
  ) {
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
  ) {
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
  ) {
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
  ) {
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
