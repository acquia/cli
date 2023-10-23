<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\Push\PushArtifactCommand;
use Acquia\Cli\Tests\Commands\Pull\PullCommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @property \Acquia\Cli\Command\Push\PushCodeCommand $command
 */
class PushArtifactCommandTest extends PullCommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(PushArtifactCommand::class);
  }

  public function testNoAuthenticationRequired(): void {
    $help = $this->command->getHelp();
    $this->assertStringNotContainsString('This command requires authentication', $help);
  }

  public function testPushArtifact(): void {
    $applications = $this->mockRequest('getApplications');
    $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
    $environments = $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, $environments[0]->vcs->path, [$environments[0]->vcs->url]);
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'y',
      // Choose an Acquia environment:
      0,
    ];
    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
  }

  public function testPushTagArtifact(): void {
    $applications = $this->mockRequest('getApplications');
    $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
    $environments = $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $gitTag = '1.2.0-build';
    $this->setUpPushArtifact($localMachineHelper, '1.2.0', [$environments[0]->vcs->url], $gitTag);
    $artifactDir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $this->mockGitTag($localMachineHelper, $gitTag, $artifactDir);
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
    ];
    $this->executeCommand([
      '--destination-git-tag' => $gitTag,
      '--source-git-tag' => '1.2.0',
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
  }

  public function testPushArtifactWithAcquiaCliFile(): void {
    $applications = $this->mockRequest('getApplications');
    $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
    $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);
    $this->datastoreAcli->set('push.artifact.destination-git-urls', [
      'https://github.com/example1/cli.git',
      'https://github.com/example2/cli.git',
    ]);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, 'master', $this->datastoreAcli->get('push.artifact.destination-git-urls'));
    $this->executeCommand([
      '--destination-git-branch' => 'master',
    ]);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
  }

  public function testPushArtifactWithArgs(): void {
    $applications = $this->mockRequest('getApplications');
    $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
    $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);
    $destinationGitUrls = [
      'https://github.com/example1/cli.git',
      'https://github.com/example2/cli.git',
    ];
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, 'master', $destinationGitUrls);
    $this->executeCommand([
      '--destination-git-branch' => 'master',
      '--destination-git-urls' => $destinationGitUrls,
    ]);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
  }

  public function testPushArtifactNoPush(): void {
    $applications = $this->mockRequest('getApplications');
    $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
    $environments = $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, $environments[0]->vcs->path, [$environments[0]->vcs->url], 'master:master', TRUE, TRUE, FALSE);
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'y',
      // Choose an Acquia environment:
      0,
    ];
    $this->executeCommand(['--no-push' => TRUE], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Initializing Git', $output);
    $this->assertStringContainsString('Adding and committing changed files', $output);
    $this->assertStringNotContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
  }

  public function testPushArtifactNoCommit(): void {
    $applications = $this->mockRequest('getApplications');
    $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
    $environments = $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, $environments[0]->vcs->path, [$environments[0]->vcs->url], 'master:master', TRUE, FALSE, FALSE);
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'y',
      // Choose an Acquia environment:
      0,
    ];
    $this->executeCommand(['--no-commit' => TRUE], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Initializing Git', $output);
    $this->assertStringNotContainsString('Adding and committing changed files', $output);
    $this->assertStringNotContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
  }

  public function testPushArtifactNoClone(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, 'nothing', [], 'something', FALSE, FALSE, FALSE);
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'y',
      // Choose an Acquia environment:
      0,
    ];
    $this->executeCommand(['--no-clone' => TRUE], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringNotContainsString('Initializing Git', $output);
    $this->assertStringNotContainsString('Adding and committing changed files', $output);
    $this->assertStringNotContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
  }

  protected function setUpPushArtifact(ObjectProphecy $localMachineHelper, string $vcsPath, array $vcsUrls, string $destGitRef = 'master:master', bool $clone = TRUE, bool $commit = TRUE, bool $push = TRUE): void {
    touch(Path::join($this->projectDir, 'composer.json'));
    mkdir(Path::join($this->projectDir, 'docroot'));
    $artifactDir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $this->createMockGitConfigFile();
    $finder = $this->mockFinder();
    $localMachineHelper->getFinder()->willReturn($finder->reveal());
    $fs = $this->prophet->prophesize(Filesystem::class);
    $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
    $this->command->localMachineHelper = $localMachineHelper->reveal();

    $this->mockExecuteGitStatus(FALSE, $localMachineHelper, $this->projectDir);
    $commitHash = 'abc123';
    $this->mockGetLocalCommitHash($localMachineHelper, $this->projectDir, $commitHash);
    $this->mockComposerInstall($localMachineHelper, $artifactDir);
    $this->mockReadComposerJson($localMachineHelper, $artifactDir);
    $localMachineHelper->checkRequiredBinariesExist(['git'])->shouldBeCalled();

    if ($clone) {
      $this->mockLocalGitConfig($localMachineHelper, $artifactDir);
      $this->mockCloneShallow($localMachineHelper, $vcsPath, $vcsUrls[0], $artifactDir);
    }
    if ($commit) {
      $this->mockGitAddCommit($localMachineHelper, $artifactDir, $commitHash);
    }
    if ($push) {
      $this->mockGitPush($vcsUrls, $localMachineHelper, $artifactDir, $destGitRef);
    }
  }

  protected function mockCloneShallow(ObjectProphecy $localMachineHelper, string $vcsPath, string $vcsUrl, mixed $artifactDir): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE)->shouldBeCalled();
    $localMachineHelper->execute(['git', 'clone', '--depth=1', $vcsUrl, $artifactDir], Argument::type('callable'), NULL, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'fetch', '--depth=1', $vcsUrl, $vcsPath . ':' . $vcsPath], Argument::type('callable'), Argument::type('string'), TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'checkout', $vcsPath], Argument::type('callable'), Argument::type('string'), TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockLocalGitConfig(ObjectProphecy $localMachineHelper, string $artifactDir): void {
    $process = $this->prophet->prophesize(Process::class);
    $localMachineHelper->execute(['git', 'config', '--local', 'core.excludesFile', 'false'], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'config', '--local', 'core.fileMode', 'true'], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockComposerInstall(ObjectProphecy $localMachineHelper, mixed $artifactDir): void {
    $localMachineHelper->checkRequiredBinariesExist(['composer'])->shouldBeCalled();
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $localMachineHelper->execute(['composer', 'install', '--no-dev', '--no-interaction', '--optimize-autoloader'], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockGitAddCommit(ObjectProphecy $localMachineHelper, mixed $artifactDir, mixed $commitHash): void {
    $process = $this->mockProcess();
    $localMachineHelper->execute(['git', 'add', '-A'], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'add', '-f', 'docroot/index.php'], NULL, $artifactDir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'add', '-f', 'docroot/autoload.php'], NULL, $artifactDir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'add', '-f', 'docroot/core'], NULL, $artifactDir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'add', '-f', 'vendor'], NULL, $artifactDir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'commit', '-m', "Automated commit by Acquia CLI (source commit: $commitHash)"], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockReadComposerJson(ObjectProphecy $localMachineHelper, string $artifactDir): void {
    $composerJson = json_encode([
      'extra' => [
        'drupal-scaffold' => [
          'file-mapping' => [
            '[web-root]/index.php' => [],
          ],
        ],
        'installer-paths' => [
          'docroot/core' => [],
        ],
],
    ]);
    $localMachineHelper->readFile(Path::join($this->projectDir, 'composer.json'))
      ->willReturn($composerJson);
    $localMachineHelper->readFile(Path::join($artifactDir, 'docroot', 'core', 'composer.json'))
      ->willReturn($composerJson);
  }

  protected function mockGitPush(array $gitUrls, ObjectProphecy $localMachineHelper, string $artifactDir, string $destGitRef): void {
    $process = $this->mockProcess();
    foreach ($gitUrls as $gitUrl) {
      $localMachineHelper->execute(['git', 'push', $gitUrl, $destGitRef], Argument::type('callable'), $artifactDir, TRUE)
        ->willReturn($process->reveal())->shouldBeCalled();
    }
  }

  protected function mockGitTag(ObjectProphecy $localMachineHelper, string $gitTag, string $artifactDir): void {
    $process = $this->mockProcess();
    $localMachineHelper->execute([
      'git',
      'tag',
      $gitTag,
    ], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

}
