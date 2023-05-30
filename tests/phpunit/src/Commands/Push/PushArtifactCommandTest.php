<?php

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

  public function testPushArtifact(): void {
    touch(Path::join($this->projectDir, 'composer.json'));
    mkdir(Path::join($this->projectDir, 'docroot'));
    $this->mockRequest('getApplications');
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, $selectedEnvironment->vcs->path, [$selectedEnvironment->vcs->url]);
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
    touch(Path::join($this->projectDir, 'composer.json'));
    mkdir(Path::join($this->projectDir, 'docroot'));
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, '1.2.0', [$selectedEnvironment->vcs->url]);
    $gitTag = '1.2.0-build';
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
    touch(Path::join($this->projectDir, 'composer.json'));
    mkdir(Path::join($this->projectDir, 'docroot'));
    $this->mockRequest('getApplications');
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applicationsResponse);
    $this->datastoreAcli->set('push.artifact.destination-git-urls', [
      'https://github.com/example1/cli.git',
      'https://github.com/example2/cli.git',
    ]);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, 'master', $this->datastoreAcli->get('push.artifact.destination-git-urls'));
    $this->executeCommand([
      '--destination-git-branch' => 'master',
    ], []);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
  }

  public function testPushArtifactWithArgs(): void {
    touch(Path::join($this->projectDir, 'composer.json'));
    mkdir(Path::join($this->projectDir, 'docroot'));
    $this->mockRequest('getApplications');
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applicationsResponse);
    $destinationGitUrls = [
      'https://github.com/example1/cli.git',
      'https://github.com/example2/cli.git',
    ];
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($localMachineHelper, 'master', $destinationGitUrls);
    $this->executeCommand([
      '--destination-git-branch' => 'master',
      '--destination-git-urls' => $destinationGitUrls,
    ], []);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
  }

  protected function setUpPushArtifact($localMachineHelper, $vcsPath, $vcsUrls): void {
    $artifactDir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $this->createMockGitConfigFile();
    $finder = $this->mockFinder();
    $localMachineHelper->getFinder()->willReturn($finder->reveal());
    $fs = $this->prophet->prophesize(Filesystem::class);
    $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();
    $this->command->localMachineHelper = $localMachineHelper->reveal();

    $commitHash = 'abc123';
    $this->mockExecuteGitStatus(FALSE, $localMachineHelper, $this->projectDir);
    $this->mockGetLocalCommitHash($localMachineHelper, $this->projectDir, $commitHash);
    $this->mockCloneShallow($localMachineHelper, $vcsPath, $vcsUrls[0], $artifactDir);
    $this->mockLocalGitConfig($localMachineHelper, $artifactDir);
    $this->mockComposerInstall($localMachineHelper, $artifactDir);
    $this->mockReadComposerJson($localMachineHelper, $artifactDir);
    $this->mockGitAddCommit($localMachineHelper, $artifactDir, $commitHash);
    $this->mockGitPush($vcsUrls, $localMachineHelper, $vcsPath, $artifactDir);
  }

  protected function mockCloneShallow(ObjectProphecy $localMachineHelper, $vcsPath, $vcsUrl, $artifactDir): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE)->shouldBeCalled();
    $localMachineHelper->checkRequiredBinariesExist(['git'])->shouldBeCalled();
    $localMachineHelper->execute(['git', 'clone', '--depth=1', $vcsUrl, $artifactDir], Argument::type('callable'), NULL, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'fetch', '--depth=1', $vcsUrl, $vcsPath . ':' . $vcsPath], Argument::type('callable'), Argument::type('string'), TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'checkout', $vcsPath], Argument::type('callable'), Argument::type('string'), TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockLocalGitConfig(ObjectProphecy $localMachineHelper, $artifactDir): void {
    $process = $this->prophet->prophesize(Process::class);
    $localMachineHelper->execute(['git', 'config', '--local', 'core.excludesFile', 'false'], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $localMachineHelper->execute(['git', 'config', '--local', 'core.fileMode', 'true'], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockComposerInstall(ObjectProphecy $localMachineHelper, $artifactDir): void {
    $localMachineHelper->checkRequiredBinariesExist(['composer'])->shouldBeCalled();
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $localMachineHelper->execute(['composer', 'install', '--no-dev', '--no-interaction', '--optimize-autoloader'], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockGitAddCommit(ObjectProphecy $localMachineHelper, $artifactDir, $commitHash): void {
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

  protected function mockGitPush($gitUrls, ObjectProphecy $localMachineHelper, $gitBranch, $artifactDir): void {
    $process = $this->mockProcess();
    foreach ($gitUrls as $gitUrl) {
      $localMachineHelper->execute(Argument::containing($gitUrl), Argument::type('callable'), $artifactDir, TRUE)
        ->willReturn($process->reveal())->shouldBeCalled();
    }
  }

  protected function mockGitTag(ObjectProphecy $localMachineHelper, $gitTag, $artifactDir): void {
    $process = $this->mockProcess();
    $localMachineHelper->execute([
      'git',
      'tag',
      $gitTag,
    ], Argument::type('callable'), $artifactDir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

}
