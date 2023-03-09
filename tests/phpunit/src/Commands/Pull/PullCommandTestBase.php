<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Class PullCommandTestBase.
 *
 * @package Acquia\Cli\Tests\Commands\Pull
 */
abstract class PullCommandTestBase extends CommandTestBase {

  use IdeRequiredTestTrait;

  public function setUp($output = NULL): void {
    parent::setUp();
  }

  protected function mockExecuteDrushExists(
    ObjectProphecy $local_machine_helper
  ): void {
    $local_machine_helper
      ->commandExists('drush')
      ->willReturn(TRUE)
      ->shouldBeCalled();
  }

  /**
   * @param $has_connection
   */
  protected function mockExecuteDrushStatus(
    ObjectProphecy $local_machine_helper,
    $has_connection,
    string $dir = NULL
  ): void {
    $drush_status_process = $this->prophet->prophesize(Process::class);
    $drush_status_process->isSuccessful()->willReturn($has_connection);
    $drush_status_process->getExitCode()->willReturn($has_connection ? 0 : 1);
    $drush_status_process->getOutput()
      ->willReturn(json_encode(['db-status' => 'Connected']));
    $local_machine_helper
      ->execute([
        'drush',
        'status',
        '--fields=db-status,drush-version',
        '--format=json',
        '--no-interaction',
      ], Argument::any(), $dir, FALSE)
      ->willReturn($drush_status_process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteDrushCacheRebuild(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $local_machine_helper
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
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $local_machine_helper
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
    ObjectProphecy $local_machine_helper
  ): void {
    $local_machine_helper
      ->commandExists('composer')
      ->willReturn(TRUE)
      ->shouldBeCalled();
  }

  protected function mockExecuteComposerInstall(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $local_machine_helper
      ->execute([
        'composer',
        'install',
        '--no-interaction',
      ], Argument::type('callable'), $this->projectDir, FALSE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockDrupalSettingsRefresh(
    ObjectProphecy $local_machine_helper
  ): void {
    $local_machine_helper
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
    ObjectProphecy $local_machine_helper,
    $cwd
  ): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(!$failed)->shouldBeCalled();
    $local_machine_helper->executeFromCmd('git add . && git diff-index --cached --quiet HEAD', NULL, $cwd, FALSE)->willReturn($process->reveal())->shouldBeCalled();
  }

  /**
   * @param $cwd
   * @param $commit_hash
   */
  protected function mockGetLocalCommitHash(
    ObjectProphecy $local_machine_helper,
    $cwd,
    $commit_hash
  ): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE)->shouldBeCalled();
    $process->getOutput()->willReturn($commit_hash)->shouldBeCalled();
    $local_machine_helper->execute([
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
