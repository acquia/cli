<?php

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\Push\PushArtifactCommand;
use Acquia\Cli\Tests\Commands\Pull\PullCommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class PushCodeCommandTest.
 *
 * @property \Acquia\Cli\Command\Push\PushCodeCommand $command
 */
class PushArtifactCommandTest extends PullCommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(PushArtifactCommand::class);
  }

  public function testPushArtifact(): void {
    $artifact_dir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $finder = $this->mockFinder();
    $local_machine_helper->getFinder()->willReturn($finder->reveal());
    $fs = $this->prophet->prophesize(Filesystem::class);
    $local_machine_helper->getFilesystem()->willReturn($fs)->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $commit_hash = 'abc123';
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $this->projectFixtureDir);
    $this->mockGetLocalCommitHash($local_machine_helper, $this->projectFixtureDir, $commit_hash);
    $this->mockCloneShallow($local_machine_helper, $selected_environment->vcs->path, $selected_environment->vcs->url, $artifact_dir);
    $this->mockLocalGitConfig($local_machine_helper, $artifact_dir);
    $this->mockComposerInstall($local_machine_helper, $artifact_dir);
    $this->mockReadComposerJson($local_machine_helper, $artifact_dir);
    $this->mockGitAddCommitPush($local_machine_helper, $artifact_dir, $commit_hash, $selected_environment->vcs->url, $selected_environment->vcs->path);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];
    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $vcs_path
   * @param $vcs_url
   * @param $artifact_dir
   */
  protected function mockCloneShallow(ObjectProphecy $local_machine_helper, $vcs_path, $vcs_url, $artifact_dir): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE)->shouldBeCalled();
    $local_machine_helper->checkRequiredBinariesExist(['git'])->shouldBeCalled();
    $local_machine_helper->execute(['git', 'clone', '--depth', '1', '--branch', $vcs_path, $vcs_url, $artifact_dir], Argument::type('callable'), NULL, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
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
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $artifact_dir
   */
  protected function mockComposerInstall(ObjectProphecy $local_machine_helper, $artifact_dir): void {
    $local_machine_helper->checkRequiredBinariesExist(['composer'])->shouldBeCalled();
    $process = $this->prophet->prophesize(Process::class);
    $local_machine_helper->execute(['composer', 'install', '--no-dev', '--no-interaction', '--optimize-autoloader'], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $artifact_dir
   * @param $commit_hash
   * @param $git_url
   * @param $git_branch
   */
  protected function mockGitAddCommitPush(ObjectProphecy $local_machine_helper, $artifact_dir, $commit_hash, $git_url, $git_branch): void {
    $process = $this->prophet->prophesize(Process::class);
    $local_machine_helper->execute(['git', 'add', '-A'], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'add', '-f', 'docroot/core/index.php'], NULL, $artifact_dir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'add', '-f', 'docroot/core'], NULL, $artifact_dir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'add', '-f', 'vendor'], NULL, $artifact_dir, FALSE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'commit', '-m', "Automated commit by Acquia CLI (source commit: $commit_hash)"], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
    $local_machine_helper->execute(['git', 'push', $git_url, $git_branch . '-build'], Argument::type('callable'), $artifact_dir, TRUE)
      ->willReturn($process->reveal())->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param string $artifact_dir
   */
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
    $local_machine_helper->readFile(Path::join($artifact_dir, 'composer.json'))
      ->willReturn($composer_json);
    $local_machine_helper->readFile(Path::join($artifact_dir, 'docroot', 'core', 'composer.json'))
      ->willReturn($composer_json);
  }

}
