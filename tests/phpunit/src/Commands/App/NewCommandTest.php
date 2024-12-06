<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\NewCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @property \Acquia\Cli\Command\App\NewCommand $command
 */
class NewCommandTest extends CommandTestBase
{
    protected string $newProjectDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->setupFsFixture();
    }

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(NewCommand::class);
    }

    /**
     * @return array<mixed>
     */
    public static function provideTestNewDrupalCommand(): array
    {
        return [
            [['acquia_drupal_recommended' => 'acquia/drupal-recommended-project']],
            [
                [
                    'acquia_drupal_recommended' => 'acquia/drupal-recommended-project',
                    'test-dir',
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public static function provideTestNewNextJsAppCommand(): array
    {
        return [
            [['acquia_next_acms' => 'acquia/next-acms']],
            [['acquia_next_acms' => 'acquia/next-acms'], 'test-dir'],
        ];
    }

    /**
     * @dataProvider provideTestNewDrupalCommand
     */
    public function testNewDrupalCommand(array $package, string $directory = 'drupal'): void
    {
        $this->newProjectDir = Path::makeAbsolute($directory, $this->projectDir);
        $projectKey = array_keys($package)[0];
        $project = $package[$projectKey];

        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $process->getExitCode()->willReturn(0);

        $localMachineHelper = $this->mockLocalMachineHelper();

        $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);
        $localMachineHelper->checkRequiredBinariesExist(["composer"])
            ->shouldBeCalled();
        $this->mockExecuteComposerCreate($this->newProjectDir, $localMachineHelper, $process, $project);
        $localMachineHelper->checkRequiredBinariesExist(["git"])
            ->shouldBeCalled();
        $this->mockExecuteGitInit($localMachineHelper, $this->newProjectDir, $process);
        $this->mockExecuteGitAdd($localMachineHelper, $this->newProjectDir, $process);
        $this->mockExecuteGitCommit($localMachineHelper, $this->newProjectDir, $process);

        $inputs = [
            // Choose a starting project.
            $project,
        ];
        $this->executeCommand([
            'directory' => $directory,
        ], $inputs);

        $output = $this->getDisplay();
        $this->assertStringContainsString('Acquia recommends most customers use acquia/drupal-recommended-project to setup a Drupal project', $output);
        $this->assertStringContainsString('Choose a starting project', $output);
        $this->assertStringContainsString($project, $output);
        $this->assertTrue($mockFileSystem->isAbsolutePath($this->newProjectDir), 'Directory path is not absolute');
        $this->assertStringContainsString('New 💧 Drupal project created in ' . $this->newProjectDir, $output);
    }

    /**
     * @dataProvider provideTestNewNextJsAppCommand
     */
    public function testNewNextJSAppCommand(array $package, string $directory = 'nextjs'): void
    {
        $this->newProjectDir = Path::makeAbsolute($directory, $this->projectDir);
        $projectKey = array_keys($package)[0];
        $project = $package[$projectKey];

        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $process->getExitCode()->willReturn(0);

        $localMachineHelper = $this->mockLocalMachineHelper();

        $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);

        $localMachineHelper->checkRequiredBinariesExist(["node"])
            ->shouldBeCalled();
        $this->mockExecuteNpxCreate($this->newProjectDir, $localMachineHelper, $process);
        $localMachineHelper->checkRequiredBinariesExist(["git"])
            ->shouldBeCalled();
        $this->mockExecuteGitInit($localMachineHelper, $this->newProjectDir, $process);
        $this->mockExecuteGitAdd($localMachineHelper, $this->newProjectDir, $process);
        $this->mockExecuteGitCommit($localMachineHelper, $this->newProjectDir, $process);

        $inputs = [
            // Choose a starting project.
            $project,
        ];
        $this->executeCommand([
            'directory' => $directory,
        ], $inputs);

        $output = $this->getDisplay();
        $this->assertStringContainsString('acquia/next-acms is a starter template for building a headless site', $output);
        $this->assertStringContainsString('Choose a starting project', $output);
        $this->assertStringContainsString($project, $output);
        $this->assertTrue($mockFileSystem->isAbsolutePath($this->newProjectDir), 'Directory path is not absolute');
        $this->assertStringContainsString('New Next.js project created in ' . $this->newProjectDir, $output);
    }

    public function testProjectTemplateOption(): void
    {
        $this->newProjectDir = Path::makeAbsolute('nextjs', $this->projectDir);

        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $process->getExitCode()->willReturn(0);

        $localMachineHelper = $this->mockLocalMachineHelper();

        $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);

        $localMachineHelper->checkRequiredBinariesExist(["node"])
            ->shouldBeCalled();
        $this->mockExecuteNpxCreate($this->newProjectDir, $localMachineHelper, $process);
        $localMachineHelper->checkRequiredBinariesExist(["git"])
            ->shouldBeCalled();
        $this->mockExecuteGitInit($localMachineHelper, $this->newProjectDir, $process);
        $this->mockExecuteGitAdd($localMachineHelper, $this->newProjectDir, $process);
        $this->mockExecuteGitCommit($localMachineHelper, $this->newProjectDir, $process);

        $this->executeCommand([
            '--template' => 'acquia_next_acms',
            'directory' => 'nextjs',
        ]);

        $output = $this->getDisplay();
        $this->assertStringContainsString('Acquia recommends most customers use acquia/drupal-recommended-project to setup a Drupal project', $output);
        $this->assertStringContainsString('acquia/next-acms', $output);
        $this->assertTrue($mockFileSystem->isAbsolutePath($this->newProjectDir), 'Directory path is not absolute');
        $this->assertStringContainsString('New Next.js project created in ' . $this->newProjectDir, $output);
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
            ->execute($command, null, $projectDir)
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
            ->execute($command, null, $projectDir)
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
            ->execute($command, null, $projectDir)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }
}
