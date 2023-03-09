<?php

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\NewCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * Class NewCommandTest.
 *
 * @property \Acquia\Cli\Command\App\NewCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class NewCommandTest extends CommandTestBase {

  protected string $newProjectDir;

  /**
   * @throws \JsonException
   */
  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->setupFsFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(NewCommand::class);
  }

  public function provideTestNewDrupalCommand(): array {
    return [
      [['acquia_drupal_recommended' => 'acquia/drupal-recommended-project']],
      [['acquia_drupal_recommended' => 'acquia/drupal-recommended-project', 'test-dir']],
    ];
  }

  public function provideTestNewNextJsAppCommand(): array {
    return [
      [['acquia_next_acms' => 'acquia/next-acms']],
      [['acquia_next_acms' => 'acquia/next-acms'], 'test-dir'],
    ];
  }

  /**
   * Tests the 'new' command for Drupal project.
   *
   * @dataProvider provideTestNewDrupalCommand
   *
   * @param array $package
   *
   * @throws \Exception
   */
  public function testNewDrupalCommand(array $package, string $directory = 'drupal'): void {
    $this->newProjectDir = Path::makeAbsolute($directory, $this->projectDir);
    $project_key = array_keys($package)[0];
    $project = $package[$project_key];

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);

    $local_machine_helper = $this->mockLocalMachineHelper();

    $mock_file_system = $this->mockGetFilesystem($local_machine_helper);
    $local_machine_helper->checkRequiredBinariesExist(["composer"])->shouldBeCalled();
    $this->mockExecuteComposerCreate($this->newProjectDir, $local_machine_helper, $process, $project);
    $local_machine_helper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $this->mockExecuteGitInit($local_machine_helper, $this->newProjectDir, $process);
    $this->mockExecuteGitAdd($local_machine_helper, $this->newProjectDir, $process);
    $this->mockExecuteGitCommit($local_machine_helper, $this->newProjectDir, $process);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $inputs = [
      // Choose a starting project
      $project,
    ];
    $this->executeCommand([
      'directory' => $directory,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Acquia recommends most customers use acquia/drupal-recommended-project to setup a Drupal project', $output);
    $this->assertStringContainsString('Choose a starting project', $output);
    $this->assertStringContainsString($project, $output);
    $this->assertTrue($mock_file_system->isAbsolutePath($this->newProjectDir), 'Directory path is not absolute');
    $this->assertStringContainsString('New 💧 Drupal project created in ' . $this->newProjectDir, $output);
  }

  /**
   * Tests the 'new' command for Next.js App.
   *
   * @dataProvider provideTestNewNextJsAppCommand
   *
   * @param array $package
   *
   * @throws \Exception
   */
  public function testNewNextJSAppCommand(array $package, string $directory = 'nextjs'): void {
    $this->newProjectDir = Path::makeAbsolute($directory, $this->projectDir);
    $project_key = array_keys($package)[0];
    $project = $package[$project_key];

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);

    $local_machine_helper = $this->mockLocalMachineHelper();

    $mock_file_system = $this->mockGetFilesystem($local_machine_helper);

    $local_machine_helper->checkRequiredBinariesExist(["node"])->shouldBeCalled();
    $this->mockExecuteNpxCreate($this->newProjectDir, $local_machine_helper, $process);
    $local_machine_helper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $this->mockExecuteGitInit($local_machine_helper, $this->newProjectDir, $process);
    $this->mockExecuteGitAdd($local_machine_helper, $this->newProjectDir, $process);
    $this->mockExecuteGitCommit($local_machine_helper, $this->newProjectDir, $process);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $inputs = [
      // Choose a starting project
      $project,
    ];
    $this->executeCommand([
      'directory' => $directory,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('acquia/next-acms is a starter template for building a headless site', $output);
    $this->assertStringContainsString('Choose a starting project', $output);
    $this->assertStringContainsString($project, $output);
    $this->assertTrue($mock_file_system->isAbsolutePath($this->newProjectDir), 'Directory path is not absolute');
    $this->assertStringContainsString('New Next JS project created in ' . $this->newProjectDir, $output);
  }

  protected function mockExecuteComposerCreate(
    string $project_dir,
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process,
    string $project
  ): void {
    $command = [
      'composer',
      'create-project',
      $project,
      $project_dir,
      '--no-interaction',
    ];
    $local_machine_helper
      ->execute($command)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteNpxCreate(
    string $project_dir,
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process,
  ): void {
    $command = [
      'npx',
      'create-next-app',
      '-e',
      'https://github.com/acquia/next-acms/tree/main/starters/basic-starter',
      $project_dir,
    ];
    $local_machine_helper
      ->execute($command)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteGitInit(
    ObjectProphecy $local_machine_helper,
    string $project_dir,
    ObjectProphecy $process
  ): void {
    $command = [
      'git',
      'init',
      '--initial-branch=main',
    ];
    $local_machine_helper
      ->execute($command, NULL, $project_dir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteGitAdd(
    ObjectProphecy $local_machine_helper,
    string $project_dir,
    ObjectProphecy $process
  ): void {
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

  protected function mockExecuteGitCommit(
    ObjectProphecy $local_machine_helper,
    string $project_dir,
    ObjectProphecy $process
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
