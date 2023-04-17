<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\Pull\PullCodeCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @property \Acquia\Cli\Command\Pull\PullCodeCommand $command
 */
class PullCodeCommandTest extends PullCommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(PullCodeCommand::class);
  }

  public function testCloneRepo(): void {
    // Unset repo root. Mimics failing to find local git repo. Command must be re-created
    // to re-inject the parameter into the command.
    $this->acliRepoRoot = '';
    $this->command = $this->createCommand();
    // Client responses.
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $dir = Path::join($this->vfsRoot->url(), 'empty-dir');
    mkdir($dir);
    $local_machine_helper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $this->mockExecuteGitClone($local_machine_helper, $selected_environment, $process, $dir);
    $this->mockExecuteGitCheckout($local_machine_helper, $selected_environment->vcs->path, $dir, $process);
    $local_machine_helper->getFinder()->willReturn(new Finder());

    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $inputs = [
      // Would you like to clone a project into the current directory?
      'y',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Choose an Acquia environment:
      0,
    ];
    $this->executeCommand([
      '--dir' => $dir,
      '--no-scripts' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
  }

  public function testPullCode(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $finder = $this->mockFinder();
    $local_machine_helper->getFinder()->willReturn($finder->reveal());
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($local_machine_helper, $process, $this->projectDir, $selected_environment->vcs->path);
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $this->projectDir);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Choose an Acquia environment:
      0,
    ];

    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
  }

  public function testWithScripts(): void {
    touch(Path::join($this->projectDir, 'composer.json'));
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $selected_environment = $environments_response->_embedded->items[0];
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $finder = $this->mockFinder();
    $local_machine_helper->getFinder()->willReturn($finder->reveal());
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($local_machine_helper, $process, $this->projectDir, $selected_environment->vcs->path);
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $this->projectDir);
    $process = $this->mockProcess();
    $this->mockExecuteComposerExists($local_machine_helper);
    $this->mockExecuteComposerInstall($local_machine_helper, $process);
    $this->mockExecuteDrushExists($local_machine_helper);
    $this->mockExecuteDrushStatus($local_machine_helper, TRUE, $this->projectDir);
    $this->mockExecuteDrushCacheRebuild($local_machine_helper, $process);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
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
  }

  public function providerTestMatchPhpVersion(): array {
    return [
      ['7.1'],
      ['7.2'],
      [''],
    ];
  }

  /**
   * @dataProvider providerTestMatchPhpVersion
   */
  public function testMatchPhpVersion(string $php_version): void {
    IdeHelper::setCloudIdeEnvVars();
    $this->application->addCommands([
      $this->injectCommand(IdePhpVersionCommand::class),
    ]);
    $this->command = $this->createCommand();
    $dir = '/home/ide/project';
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper->checkRequiredBinariesExist(["git"])
      ->shouldBeCalled();
    $finder = $this->mockFinder();
    $local_machine_helper->getFinder()->willReturn($finder->reveal());
    $local_machine_helper->readFile('/home/ide/configs/php/.version')->willReturn($php_version . "\n")->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($local_machine_helper, $process, $dir, 'master');
    $this->mockExecuteGitStatus(FALSE, $local_machine_helper, $dir);

    $environment_response = $this->getMockEnvironmentResponse();
    $environment_response->configuration->php->version = '7.1';
    $environment_response->sshUrl = $environment_response->ssh_url;
    $this->clientProphecy->request('get',
      "/environments/" . $environment_response->id)
      ->willReturn($environment_response)
      ->shouldBeCalled();

    $this->executeCommand([
      '--dir' => $dir,
      '--no-scripts' => TRUE,
      // @todo Execute ONLY match php aspect, not the code pull.
      'environmentId' => $environment_response->id,
    ], [
      // Choose an Acquia environment:
      0,
      // Would you like to change the PHP version on this IDE to match the PHP version on ... ?
      'n',
    ]);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    IdeHelper::unsetCloudIdeEnvVars();
    $message = "Would you like to change the PHP version on this IDE to match the PHP version on the {$environment_response->label} ({$environment_response->configuration->php->version}) environment?";
    if ($php_version === '7.1') {
      $this->assertStringNotContainsString($message, $output);
    }
    else {
      $this->assertStringContainsString($message, $output);
    }
  }

  /**
   * @param $dir
   */
  protected function mockExecuteGitClone(
    ObjectProphecy $local_machine_helper,
    object $environments_response,
    ObjectProphecy $process,
                   $dir
  ): void {
    $command = [
      'git',
      'clone',
      $environments_response->vcs->url,
      $dir,
    ];
    $local_machine_helper->execute($command, Argument::type('callable'), NULL, TRUE, NULL, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=no'])
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param $cwd
   * @param $vcs_path
   */
  protected function mockExecuteGitFetchAndCheckout(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process,
    $cwd,
    $vcs_path
  ): void {
    $local_machine_helper->execute([
      'git',
      'fetch',
      '--all',
    ], Argument::type('callable'), $cwd, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->mockExecuteGitCheckout($local_machine_helper, $vcs_path, $cwd, $process);
  }

  /**
   * @param $vcs_path
   * @param $cwd
   */
  protected function mockExecuteGitCheckout(ObjectProphecy $local_machine_helper, $vcs_path, $cwd, ObjectProphecy $process): void {
    $local_machine_helper->execute([
      'git',
      'checkout',
      $vcs_path,
    ], Argument::type('callable'), $cwd, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
