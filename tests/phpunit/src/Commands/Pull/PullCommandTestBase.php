<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

abstract class PullCommandTestBase extends CommandTestBase {

  use IdeRequiredTestTrait;

  public function setUp($output = NULL): void {
    parent::setUp();
  }

  protected function mockExecuteDrushExists(
    ObjectProphecy $localMachineHelper
  ): void {
    $localMachineHelper
      ->commandExists('drush')
      ->willReturn(TRUE)
      ->shouldBeCalled();
  }

  /**
   * @param $hasConnection
   */
  protected function mockExecuteDrushStatus(
    ObjectProphecy $localMachineHelper,
    $hasConnection,
    string $dir = NULL
  ): void {
    $drushStatusProcess = $this->prophet->prophesize(Process::class);
    $drushStatusProcess->isSuccessful()->willReturn($hasConnection);
    $drushStatusProcess->getExitCode()->willReturn($hasConnection ? 0 : 1);
    $drushStatusProcess->getOutput()
      ->willReturn(json_encode(['db-status' => 'Connected']));
    $localMachineHelper
      ->execute([
        'drush',
        'status',
        '--fields=db-status,drush-version',
        '--format=json',
        '--no-interaction',
      ], Argument::any(), $dir, FALSE)
      ->willReturn($drushStatusProcess->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteDrushCacheRebuild(
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process
  ): void {
    $localMachineHelper
      ->execute([
        'drush',
        'cache:rebuild',
        '--yes',
        '--no-interaction',
        '--verbose',
      ], Argument::type('callable'), $this->projectDir, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteDrushSqlSanitize(
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process
  ): void {
    $localMachineHelper
      ->execute([
        'drush',
        'sql:sanitize',
        '--yes',
        '--no-interaction',
        '--verbose',
      ], Argument::type('callable'), $this->projectDir, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteComposerExists(
    ObjectProphecy $localMachineHelper
  ): void {
    $localMachineHelper
      ->commandExists('composer')
      ->willReturn(TRUE)
      ->shouldBeCalled();
  }

  protected function mockExecuteComposerInstall(
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process
  ): void {
    $localMachineHelper
      ->execute([
        'composer',
        'install',
        '--no-interaction',
      ], Argument::type('callable'), $this->projectDir, FALSE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockDrupalSettingsRefresh(
    ObjectProphecy $localMachineHelper
  ): void {
    $localMachineHelper
      ->execute([
        '/ide/drupal-setup.sh',
      ]);
  }

  /**
   * @param $failed
   * @param $cwd
   */
  protected function mockExecuteGitStatus(
    $failed,
    ObjectProphecy $localMachineHelper,
    $cwd
  ): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(!$failed)->shouldBeCalled();
    $localMachineHelper->executeFromCmd('git add . && git diff-index --cached --quiet HEAD', NULL, $cwd, FALSE)->willReturn($process->reveal())->shouldBeCalled();
  }

  /**
   * @param $cwd
   * @param $commitHash
   */
  protected function mockGetLocalCommitHash(
    ObjectProphecy $localMachineHelper,
    $cwd,
    $commitHash
  ): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE)->shouldBeCalled();
    $process->getOutput()->willReturn($commitHash)->shouldBeCalled();
    $localMachineHelper->execute([
      'git',
      'rev-parse',
      'HEAD',
    ], NULL, $cwd, FALSE)->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockFinder(): ObjectProphecy {
    $finder = $this->prophet->prophesize(Finder::class);
    $finder->files()->willReturn($finder);
    $finder->in(Argument::type('string'))->willReturn($finder);
    $finder->in(Argument::type('array'))->willReturn($finder);
    $finder->ignoreDotFiles(FALSE)->willReturn($finder);
    $finder->ignoreVCS(FALSE)->willReturn($finder);
    $finder->ignoreVCSIgnored(TRUE)->willReturn($finder);
    $finder->hasResults()->willReturn(TRUE);
    $finder->name(Argument::type('string'))->willReturn($finder);
    $finder->notName(Argument::type('string'))->willReturn($finder);
    $finder->directories()->willReturn($finder);
    $finder->append(Argument::type(Finder::class))->willReturn($finder);

    return $finder;
  }

}
