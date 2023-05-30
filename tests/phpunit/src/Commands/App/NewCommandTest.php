<?php

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\NewCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @property \Acquia\Cli\Command\App\NewCommand $command
 */
class NewCommandTest extends CommandTestBase {

  protected string $newProjectDir;

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->setupFsFixture();
  }

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
   * @dataProvider provideTestNewDrupalCommand
   * @param array $package
   */
  public function testNewDrupalCommand(array $package, string $directory = 'drupal'): void {
    $this->newProjectDir = Path::makeAbsolute($directory, $this->projectDir);
    $projectKey = array_keys($package)[0];
    $project = $package[$projectKey];

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);

    $localMachineHelper = $this->mockLocalMachineHelper();

    $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);
    $localMachineHelper->checkRequiredBinariesExist(["composer"])->shouldBeCalled();
    $this->mockExecuteComposerCreate($this->newProjectDir, $localMachineHelper, $process, $project);
    $localMachineHelper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $this->mockExecuteGitInit($localMachineHelper, $this->newProjectDir, $process);
    $this->mockExecuteGitAdd($localMachineHelper, $this->newProjectDir, $process);
    $this->mockExecuteGitCommit($localMachineHelper, $this->newProjectDir, $process);

    $this->command->localMachineHelper = $localMachineHelper->reveal();
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
    $this->assertTrue($mockFileSystem->isAbsolutePath($this->newProjectDir), 'Directory path is not absolute');
    $this->assertStringContainsString('New 💧 Drupal project created in ' . $this->newProjectDir, $output);
  }

  /**
   * @dataProvider provideTestNewNextJsAppCommand
   * @param array $package
   */
  public function testNewNextJSAppCommand(array $package, string $directory = 'nextjs'): void {
    $this->newProjectDir = Path::makeAbsolute($directory, $this->projectDir);
    $projectKey = array_keys($package)[0];
    $project = $package[$projectKey];

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);

    $localMachineHelper = $this->mockLocalMachineHelper();

    $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);

    $localMachineHelper->checkRequiredBinariesExist(["node"])->shouldBeCalled();
    $this->mockExecuteNpxCreate($this->newProjectDir, $localMachineHelper, $process);
    $localMachineHelper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $this->mockExecuteGitInit($localMachineHelper, $this->newProjectDir, $process);
    $this->mockExecuteGitAdd($localMachineHelper, $this->newProjectDir, $process);
    $this->mockExecuteGitCommit($localMachineHelper, $this->newProjectDir, $process);

    $this->command->localMachineHelper = $localMachineHelper->reveal();
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
    $this->assertTrue($mockFileSystem->isAbsolutePath($this->newProjectDir), 'Directory path is not absolute');
    $this->assertStringContainsString('New Next JS project created in ' . $this->newProjectDir, $output);
  }

  protected function mockExecuteComposerCreate(
    string $projectDir,
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process,
    string $project
  ): void {
    $command = [
      'composer',
      'create-project',
      $project,
      $projectDir,
      '--no-interaction',
    ];
    $localMachineHelper
      ->execute($command)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteNpxCreate(
    string $projectDir,
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process,
  ): void {
    $command = [
      'npx',
      'create-next-app',
      '-e',
      'https://github.com/acquia/next-acms/tree/main/starters/basic-starter',
      $projectDir,
    ];
    $localMachineHelper
      ->execute($command)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteGitInit(
    ObjectProphecy $localMachineHelper,
    string $projectDir,
    ObjectProphecy $process
  ): void {
    $command = [
      'git',
      'init',
      '--initial-branch=main',
    ];
    $localMachineHelper
      ->execute($command, NULL, $projectDir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteGitAdd(
    ObjectProphecy $localMachineHelper,
    string $projectDir,
    ObjectProphecy $process
  ): void {
    $command = [
      'git',
      'add',
      '-A',
    ];
    $localMachineHelper
      ->execute($command, NULL, $projectDir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteGitCommit(
    ObjectProphecy $localMachineHelper,
    string $projectDir,
    ObjectProphecy $process
  ): void {
    $command = [
      'git',
      'commit',
      '--message',
      'Initial commit.',
      '--quiet',
    ];
    $localMachineHelper
      ->execute($command, NULL, $projectDir)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
