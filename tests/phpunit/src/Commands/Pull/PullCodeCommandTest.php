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
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $localMachineHelper = $this->mockReadIdePhpVersion();
    $process = $this->mockProcess();
    $dir = Path::join($this->vfsRoot->url(), 'empty-dir');
    mkdir($dir);
    $localMachineHelper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $this->mockExecuteGitClone($localMachineHelper, $selectedEnvironment, $process, $dir);
    $this->mockExecuteGitCheckout($localMachineHelper, $selectedEnvironment->vcs->path, $dir, $process);
    $localMachineHelper->getFinder()->willReturn(new Finder());

    $this->command->localMachineHelper = $localMachineHelper->reveal();

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
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $this->createMockGitConfigFile();

    $localMachineHelper = $this->mockReadIdePhpVersion();
    $localMachineHelper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $finder = $this->mockFinder();
    $localMachineHelper->getFinder()->willReturn($finder->reveal());
    $this->command->localMachineHelper = $localMachineHelper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $this->projectDir, $selectedEnvironment->vcs->path);
    $this->mockExecuteGitStatus(FALSE, $localMachineHelper, $this->projectDir);

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
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $this->createMockGitConfigFile();

    $localMachineHelper = $this->mockReadIdePhpVersion();
    $localMachineHelper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $finder = $this->mockFinder();
    $localMachineHelper->getFinder()->willReturn($finder->reveal());
    $this->command->localMachineHelper = $localMachineHelper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $this->projectDir, $selectedEnvironment->vcs->path);
    $this->mockExecuteGitStatus(FALSE, $localMachineHelper, $this->projectDir);
    $process = $this->mockProcess();
    $this->mockExecuteComposerExists($localMachineHelper);
    $this->mockExecuteComposerInstall($localMachineHelper, $process);
    $this->mockExecuteDrushExists($localMachineHelper);
    $this->mockExecuteDrushStatus($localMachineHelper, TRUE, $this->projectDir);
    $this->mockExecuteDrushCacheRebuild($localMachineHelper, $process);

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
  public function testMatchPhpVersion(string $phpVersion): void {
    IdeHelper::setCloudIdeEnvVars();
    $this->application->addCommands([
      $this->injectCommand(IdePhpVersionCommand::class),
    ]);
    $this->command = $this->createCommand();
    $dir = '/home/ide/project';
    $this->createMockGitConfigFile();

    $localMachineHelper = $this->mockReadIdePhpVersion($phpVersion);
    $localMachineHelper->checkRequiredBinariesExist(["git"])
      ->shouldBeCalled();
    $finder = $this->mockFinder();
    $localMachineHelper->getFinder()->willReturn($finder->reveal());
    $this->command->localMachineHelper = $localMachineHelper->reveal();

    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $dir, 'master');
    $this->mockExecuteGitStatus(FALSE, $localMachineHelper, $dir);

    $environmentResponse = $this->getMockEnvironmentResponse();
    $environmentResponse->configuration->php->version = '7.1';
    $environmentResponse->sshUrl = $environmentResponse->ssh_url;
    $this->clientProphecy->request('get',
      "/environments/" . $environmentResponse->id)
      ->willReturn($environmentResponse)
      ->shouldBeCalled();

    $this->executeCommand([
      '--dir' => $dir,
      '--no-scripts' => TRUE,
      // @todo Execute ONLY match php aspect, not the code pull.
      'environmentId' => $environmentResponse->id,
    ], [
      // Choose an Acquia environment:
      0,
      // Would you like to change the PHP version on this IDE to match the PHP version on ... ?
      'n',
    ]);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    IdeHelper::unsetCloudIdeEnvVars();
    $message = "Would you like to change the PHP version on this IDE to match the PHP version on the {$environmentResponse->label} ({$environmentResponse->configuration->php->version}) environment?";
    if ($phpVersion === '7.1') {
      $this->assertStringNotContainsString($message, $output);
    }
    else {
      $this->assertStringContainsString($message, $output);
    }
  }

  protected function mockExecuteGitClone(
    ObjectProphecy $localMachineHelper,
    object $environmentsResponse,
    ObjectProphecy $process,
                   $dir
  ): void {
    $command = [
      'git',
      'clone',
      $environmentsResponse->vcs->url,
      $dir,
    ];
    $localMachineHelper->execute($command, Argument::type('callable'), NULL, TRUE, NULL, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=no'])
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecuteGitFetchAndCheckout(
    ObjectProphecy $localMachineHelper,
    ObjectProphecy $process,
    $cwd,
    $vcsPath
  ): void {
    $localMachineHelper->execute([
      'git',
      'fetch',
      '--all',
    ], Argument::type('callable'), $cwd, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->mockExecuteGitCheckout($localMachineHelper, $vcsPath, $cwd, $process);
  }

  protected function mockExecuteGitCheckout(ObjectProphecy $localMachineHelper, $vcsPath, $cwd, ObjectProphecy $process): void {
    $localMachineHelper->execute([
      'git',
      'checkout',
      $vcsPath,
    ], Argument::type('callable'), $cwd, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

}
