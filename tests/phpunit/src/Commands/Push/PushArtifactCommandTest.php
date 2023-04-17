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
    $this->mockApplicationsRequest();
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($local_machine_helper, $selected_environment->vcs->path, [$selected_environment->vcs->url]);
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
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($local_machine_helper, '1.2.0', [$selected_environment->vcs->url]);
    $git_tag = '1.2.0-build';
    $artifact_dir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $this->mockGitTag($local_machine_helper, $git_tag, $artifact_dir);
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
    ];
    $this->executeCommand([
      '--destination-git-tag' => $git_tag,
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
    $this->mockApplicationsRequest();
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applications_response);
    $this->datastoreAcli->set('push.artifact.destination-git-urls', [
      'https://github.com/example1/cli.git',
      'https://github.com/example2/cli.git',
    ]);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($local_machine_helper, 'master', $this->datastoreAcli->get('push.artifact.destination-git-urls'));
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
    $this->mockApplicationsRequest();
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applications_response);
    $destination_git_urls = [
      'https://github.com/example1/cli.git',
      'https://github.com/example2/cli.git',
    ];
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->setUpPushArtifact($local_machine_helper, 'master', $destination_git_urls);
    $this->executeCommand([
      '--destination-git-urls' => $destination_git_urls,
      '--destination-git-branch' => 'master',
    ], []);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
    $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
  }

  /**
   * @param $local_machine_helper
   * @param $vcs_path
   * @param $vcs_urls
   */
  protected function setUpPushArtifact($local_machine_helper, $vcs_path, $vcs_urls): void {
    $artifact_dir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $this->createMockGitConfigFile();
    $finder = $this->mockFinder();
    $local_machine_helper->getFinder()->willReturn($finder->reveal());
    $fs = $this->prophet->prophesize(Filesystem::class);
    $local_machine_helper->getFilesystem()->willReturn($fs)->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $commit_hash = 'abc123';
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $this->projectDir);
    $this->mockGetLocalCommitHash($local_machine_helper, $this->projectDir, $commit_hash);
    $this->mockCloneShallow($local_machine_helper, $vcs_path, $vcs_urls[0], $artifact_dir);
    $this->mockLocalGitConfig($local_machine_helper, $artifact_dir);
    $this->mockComposerInstall($local_machine_helper, $artifact_dir);
    $this->mockReadComposerJson($local_machine_helper, $artifact_dir);
    $this->mockGitAddCommit($local_machine_helper, $artifact_dir, $commit_hash);
    $this->mockGitPush($vcs_urls, $local_machine_helper, $vcs_path, $artifact_dir);
  }

  /**
   * @param $vcs_path
   * @param $vcs_url
   * @param $artifact_dir
   */
  protected function mockCloneShallow(ObjectProphecy $local_machine_helper, $vcs_path, $vcs_url, $artifact_dir): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE)->shouldBeCalled();
    $local_machine_helper->checkRequiredBinariesExist(['git'])->shouldBeCalled();
    $local_machine_helper->execute(['git', 'clone', '--depth=1', $vcs_url, $artifact_dir], Argument::type('callable'), NULL, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'fetch', '--depth=1', $vcs_url, $vcs_path . ':' . $vcs_path], Argument::type('callable'), Argument::type('string'), TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'checkout', $vcs_path], Argument::type('callable'), Argument::type('string'), TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  /**
   * @param $artifact_dir
   */
  protected function mockLocalGitConfig(ObjectProphecy $local_machine_helper, $artifact_dir): void {
    $process = $this->prophet->prophesize(Process::class);
    $local_machine_helper->execute(['git', 'config', '--local', 'core.excludesFile', 'false'], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'config', '--local', 'core.fileMode', 'true'], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  /**
   * @param $artifact_dir
   */
  protected function mockComposerInstall(ObjectProphecy $local_machine_helper, $artifact_dir): void {
    $local_machine_helper->checkRequiredBinariesExist(['composer'])->shouldBeCalled();
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $local_machine_helper->execute(['composer', 'install', '--no-dev', '--no-interaction', '--optimize-autoloader'], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  /**
   * @param $artifact_dir
   * @param $commit_hash
   * @param $git_url
   * @param $git_branch
   */
  protected function mockGitAddCommit(ObjectProphecy $local_machine_helper, $artifact_dir, $commit_hash): void {
    $process = $this->mockProcess();
    $local_machine_helper->execute(['git', 'add', '-A'], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'add', '-f', 'docroot/index.php'], NULL, $artifact_dir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'add', '-f', 'docroot/autoload.php'], NULL, $artifact_dir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'add', '-f', 'docroot/core'], NULL, $artifact_dir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'add', '-f', 'vendor'], NULL, $artifact_dir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'commit', '-m', "Automated commit by Acquia CLI (source commit: $commit_hash)"], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  protected function mockReadComposerJson(ObjectProphecy $local_machine_helper, string $artifact_dir): void {
    $composer_json = json_encode([
      'extra' => [
        'installer-paths' => [
          'docroot/core' => []
        ],
        'drupal-scaffold' => [
          'file-mapping' => [
            '[web-root]/index.php' => []
          ]
        ]
      ]
    ]);
    $local_machine_helper->readFile(Path::join($this->projectDir, 'composer.json'))
      ->willReturn($composer_json);
    $local_machine_helper->readFile(Path::join($artifact_dir, 'docroot', 'core', 'composer.json'))
      ->willReturn($composer_json);
  }

  /**
   * @param $git_urls
   * @param $git_branch
   * @param $artifact_dir
   */
  protected function mockGitPush($git_urls, ObjectProphecy $local_machine_helper, $git_branch, $artifact_dir): void {
    $process = $this->mockProcess();
    foreach ($git_urls as $git_url) {
      $local_machine_helper->execute(Argument::containing($git_url), Argument::type('callable'), $artifact_dir, TRUE)
        ->willReturn($process->reveal())->shouldBeCalled();
    }
  }

  /**
   * @param $git_tag
   * @param $artifact_dir
   */
  protected function mockGitTag(ObjectProphecy $local_machine_helper, $git_tag, $artifact_dir): void {
    $process = $this->mockProcess();
    $local_machine_helper->execute([
      'git',
      'tag',
      $git_tag,
    ], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

}
