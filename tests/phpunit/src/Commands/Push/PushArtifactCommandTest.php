<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Push\PushArtifactCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Pull\PullCommandTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @property \Acquia\Cli\Command\Push\PushCodeCommand $command
 */
class PushArtifactCommandTest extends PullCommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(PushArtifactCommand::class);
    }

    public function testPushArtifactHelp(): void
    {
        $help = $this->command->getHelp();
        $expectedHelp = <<<EOF
This command builds a sanitized deploy artifact by running <options=bold>composer install</>, removing sensitive files, and committing vendor directories.

Vendor directories and scaffold files are committed to the build artifact even if they are ignored in the source repository.

To run additional build or sanitization steps (e.g. <options=bold>npm install</>), add a <options=bold>post-install-cmd</> script to your <options=bold>composer.json</> file: https://getcomposer.org/doc/articles/scripts.md#command-events

This command is designed for a specific scenario in which there are two branches or repositories involved: a source branch without vendor files committed, and an artifact branch with them. If both your source and destination branches are the same, you should simply use git push instead.
EOF;
        self::assertStringEqualsStringIgnoringLineEndings($expectedHelp, $help);
        $this->assertStringNotContainsString('This command requires authentication', $help);
    }

    /**
     * @return mixed[]
     */
    public static function providerTestPushArtifact(): array
    {
        return [
            [OutputInterface::VERBOSITY_NORMAL, false],
            [OutputInterface::VERBOSITY_VERY_VERBOSE, true],
        ];
    }

    #[DataProvider('providerTestPushArtifact')]
    public function testPushArtifact(int $verbosity, bool $printOutput): void
    {
        $applications = $this->mockRequest('getApplications');
        $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
        $environments = $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->setUpPushArtifact($localMachineHelper, $environments[0]->vcs->path, [$environments[0]->vcs->url], 'master:master', true, true, true, $printOutput);
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
        $this->executeCommand([], $inputs, $verbosity);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
        $this->assertStringNotContainsString('Production, prod', $output);
        $this->assertStringContainsString('Acquia CLI will:', $output);
        $this->assertStringContainsString('- git clone master from site@svn-3.hosted.acquia-sites.com:site.git', $output);
        $this->assertStringContainsString('- Compile the contents of vfs://root/project into an artifact', $output);
        $this->assertStringContainsString('- Copy the artifact files into the checked out copy of master', $output);
        $this->assertStringContainsString('- Commit changes and push the master branch', $output);
        if ($printOutput) {
            $this->assertStringContainsString('Removing', $output);
            $this->assertStringContainsString('Initializing Git', $output);
            $this->assertStringContainsString('Global .gitignore file', $output);
            $this->assertStringContainsString('Removing vendor', $output);
            $this->assertStringContainsString('Mirroring source', $output);
            $this->assertStringContainsString('Installing Composer', $output);
            $this->assertStringContainsString('Finding Drupal', $output);
            $this->assertStringContainsString('Removing sensitive', $output);
            $this->assertStringContainsString('Adding and committing', $output);
            $this->assertStringContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
        }
    }

    public function testPushTagArtifact(): void
    {
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

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
        $this->assertStringContainsString('Commit changes and push the 1.2.0-build tag', $output);
    }

    public function testPushArtifactWithAcquiaCliFile(): void
    {
        $destinationGitUrls = [
            'https://github.com/example1/cli.git',
            'https://github.com/example2/cli.git',
        ];
        $this->createMockAcliConfigFile(['push' => ['artifact' => ['destination_git_urls' => $destinationGitUrls],],]);
        $this->createDataStores();
        $this->command = $this->injectCommand(PushArtifactCommand::class);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->setUpPushArtifact($localMachineHelper, 'master', $destinationGitUrls);
        $this->executeCommand([
            '--destination-git-branch' => 'master',
        ]);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
    }

    public function testPushArtifactWithArgs(): void
    {
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

        $output = $this->getDisplay();

        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
    }

    public function testPushArtifactToDivergedRemotes(): void
    {
        $destinationGitUrls = [
            'https://github.com/example1/cli.git',
            'https://github.com/example2/cli.git',
        ];
        $localMachineHelper = $this->mockLocalMachineHelper();
        touch(Path::join($this->projectDir, 'composer.json'));
        mkdir(Path::join($this->projectDir, 'docroot'));
        $this->createMockGitConfigFile();
        $fs = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()->willReturn($fs);
        $this->mockExecuteGitStatus(false, $localMachineHelper, $this->projectDir);
        $this->mockGetLocalCommitHash($localMachineHelper, $this->projectDir, 'abc123');
        $localMachineHelper->checkRequiredBinariesExist(['git'])
            ->shouldBeCalled();
        $artifactDir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
        $this->mockCloneShallow($localMachineHelper, 'master', $destinationGitUrls, $artifactDir, true, [
            $destinationGitUrls[0] => 'sha1',
            $destinationGitUrls[1] => 'sha2',
        ]);
        $this->mockGitDeepen($localMachineHelper, 'master', $destinationGitUrls);
        $this->mockGitMergeBase($localMachineHelper, 'sha2', 'sha1', false);
        $this->mockGitMergeBase($localMachineHelper, 'sha1', 'sha2', false);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches('/out of sync/');
        $this->executeCommand([
            '--destination-git-branch' => 'master',
            '--destination-git-urls' => $destinationGitUrls,
        ]);
    }

    public function testPushArtifactWithBranchMissingOnFirstRemote(): void
    {
        $destinationGitUrls = [
            'https://github.com/example1/cli.git',
            'https://github.com/example2/cli.git',
        ];
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->setUpPushArtifact($localMachineHelper, 'master', $destinationGitUrls, 'master:master', true, true, true, true, [
            $destinationGitUrls[0] => null,
            $destinationGitUrls[1] => 'secondremotesha',
        ]);
        $this->executeCommand([
            '--destination-git-branch' => 'master',
            '--destination-git-urls' => $destinationGitUrls,
        ]);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
    }

    public function testPushArtifactWithRemoteBehind(): void
    {
        $destinationGitUrls = [
            'https://github.com/example1/cli.git',
            'https://github.com/example2/cli.git',
        ];
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->setUpPushArtifact($localMachineHelper, 'master', $destinationGitUrls, 'master:master', true, true, true, true, [
            $destinationGitUrls[0] => 'newsha',
            $destinationGitUrls[1] => 'oldsha',
        ]);
        $this->mockGitDeepen($localMachineHelper, 'master', $destinationGitUrls);
        $this->mockGitMergeBase($localMachineHelper, 'oldsha', 'newsha', true);
        $this->mockGitCheckoutBase($localMachineHelper, 'master', 'newsha');
        $this->executeCommand([
            '--destination-git-branch' => 'master',
            '--destination-git-urls' => $destinationGitUrls,
        ]);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
    }

    public function testPushArtifactWithNewBranchOnAllRemotes(): void
    {
        $destinationGitUrls = [
            'https://github.com/example1/cli.git',
            'https://github.com/example2/cli.git',
        ];
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->setUpPushArtifact($localMachineHelper, 'feature-1-build', $destinationGitUrls, 'feature-1-build:feature-1-build', true, true, true, true, [
            $destinationGitUrls[0] => null,
            $destinationGitUrls[1] => null,
        ]);
        $this->executeCommand([
            '--destination-git-branch' => 'feature-1-build',
            '--destination-git-urls' => $destinationGitUrls,
        ]);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example1/cli.git)', $output);
        $this->assertStringContainsString('Pushing changes to Acquia Git (https://github.com/example2/cli.git)', $output);
    }

    public function testPushArtifactPushFailureStillPushesRemainingRemotes(): void
    {
        $destinationGitUrls = [
            'https://github.com/example1/cli.git',
            'https://github.com/example2/cli.git',
        ];
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->setUpPushArtifact($localMachineHelper, 'master', $destinationGitUrls, 'master:master', true, true, false);
        $artifactDir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
        $this->mockGitPush($destinationGitUrls, $localMachineHelper, $artifactDir, 'master:master', true, [$destinationGitUrls[0]]);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches('~https://github\.com/example1/cli\.git~');
        $this->executeCommand([
            '--destination-git-branch' => 'master',
            '--destination-git-urls' => $destinationGitUrls,
        ]);
    }

    public function testPushArtifactNoPush(): void
    {
        $applications = $this->mockRequest('getApplications');
        $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
        $environments = $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->setUpPushArtifact($localMachineHelper, $environments[0]->vcs->path, [$environments[0]->vcs->url], 'master:master', true, true, false);
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
        $this->executeCommand(['--no-push' => true], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Initializing Git', $output);
        $this->assertStringContainsString('Adding and committing changed files', $output);
        $this->assertStringNotContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
    }

    public function testPushArtifactNoCommit(): void
    {
        $applications = $this->mockRequest('getApplications');
        $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
        $environments = $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->setUpPushArtifact($localMachineHelper, $environments[0]->vcs->path, [$environments[0]->vcs->url], 'master:master', true, false, false);
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
        $this->executeCommand(['--no-commit' => true], $inputs);

        $output = $this->getDisplay();

        $this->assertStringContainsString('Initializing Git', $output);
        $this->assertStringNotContainsString('Adding and committing changed files', $output);
        $this->assertStringNotContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
    }

    public function testPushArtifactNoClone(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->setUpPushArtifact($localMachineHelper, 'nothing', [], 'something', false, false, false);
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
        $this->executeCommand(['--no-clone' => true], $inputs);

        $output = $this->getDisplay();

        $this->assertStringNotContainsString('Initializing Git', $output);
        $this->assertStringNotContainsString('Adding and committing changed files', $output);
        $this->assertStringNotContainsString('Pushing changes to Acquia Git (site@svn-3.hosted.acquia-sites.com:site.git)', $output);
    }
    protected function setUpPushArtifact(ObjectProphecy $localMachineHelper, string $vcsPath, array $vcsUrls, string $destGitRef = 'master:master', bool $clone = true, bool $commit = true, bool $push = true, bool $printOutput = true, ?array $tips = null): void
    {
        touch(Path::join($this->projectDir, 'composer.json'));
        mkdir(Path::join($this->projectDir, 'docroot'));
        $artifactDir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
        $this->createMockGitConfigFile();
        $finder = $this->mockFinder();
        $localMachineHelper->getFinder()->willReturn($finder->reveal());
        $fs = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()->willReturn($fs)->shouldBeCalled();

        $this->mockExecuteGitStatus(false, $localMachineHelper, $this->projectDir);
        $commitHash = 'abc123';
        $this->mockGetLocalCommitHash($localMachineHelper, $this->projectDir, $commitHash);
        $this->mockComposerInstall($localMachineHelper, $artifactDir, $printOutput);
        $this->mockReadComposerJson($localMachineHelper, $artifactDir);
        $localMachineHelper->checkRequiredBinariesExist(['git'])
            ->shouldBeCalled();

        if ($clone) {
            $this->mockLocalGitConfig($localMachineHelper, $artifactDir, $printOutput);
            $this->mockCloneShallow($localMachineHelper, $vcsPath, $vcsUrls, $artifactDir, $printOutput, $tips);
        }
        if ($commit) {
            $this->mockGitAddCommit($localMachineHelper, $artifactDir, $commitHash, $printOutput);
        }
        if ($push) {
            $this->mockGitPush($vcsUrls, $localMachineHelper, $artifactDir, $destGitRef, $printOutput);
        }
    }

    /**
     * @param array<string, string|null>|null $tips
     *   Map of vcs url to the branch tip sha on that remote. A null value
     *   means the branch does not exist on that remote. Defaults to every
     *   url sharing the same tip.
     */
    protected function mockCloneShallow(ObjectProphecy $localMachineHelper, string $vcsPath, array $vcsUrls, string $artifactDir, bool $printOutput = true, ?array $tips = null): void
    {
        if ($tips === null) {
            $tips = array_fill_keys($vcsUrls, 'mainbranchsha');
        }
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true)->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'clone',
            '--depth=1',
            $vcsUrls[0],
            $artifactDir,
        ], Argument::type('callable'), null, $printOutput)
            ->willReturn($process->reveal())->shouldBeCalled();

        $revParseProcesses = [];
        foreach ($vcsUrls as $vcsUrl) {
            $tip = $tips[$vcsUrl];
            $fetchProcess = $this->mockProcess($tip !== null);
            $localMachineHelper->execute([
                'git',
                'fetch',
                '--depth=1',
                $vcsUrl,
                $vcsPath,
            ], Argument::type('callable'), Argument::type('string'), $printOutput)
                ->willReturn($fetchProcess->reveal())->shouldBeCalled();
            if ($tip !== null) {
                $revParseProcess = $this->mockProcess();
                $revParseProcess->getOutput()->willReturn($tip . PHP_EOL);
                $revParseProcesses[] = $revParseProcess->reveal();
            }
        }
        if ($revParseProcesses !== []) {
            $localMachineHelper->execute([
                'git',
                'rev-parse',
                'FETCH_HEAD',
            ], null, Argument::type('string'), false)
                ->willReturn(...$revParseProcesses)->shouldBeCalled();
        }

        $uniqueTips = array_values(array_unique(array_filter($tips, static fn ($tip) => $tip !== null)));
        if ($uniqueTips === []) {
            $localMachineHelper->execute([
                'git',
                'checkout',
                '-b',
                $vcsPath,
            ], Argument::type('callable'), Argument::type('string'), $printOutput)
                ->willReturn($process->reveal())->shouldBeCalled();
        } elseif (count($uniqueTips) === 1) {
            $this->mockGitCheckoutBase($localMachineHelper, $vcsPath, $uniqueTips[0], $printOutput);
        }
        // Multiple distinct tips: the test mocks deepen, merge-base, and
        // checkout calls itself.
    }

    protected function mockGitCheckoutBase(ObjectProphecy $localMachineHelper, string $vcsPath, string $baseTip, bool $printOutput = true): void
    {
        $process = $this->mockProcess();
        $localMachineHelper->execute([
            'git',
            'checkout',
            '-B',
            $vcsPath,
            $baseTip,
        ], Argument::type('callable'), Argument::type('string'), $printOutput)
            ->willReturn($process->reveal())->shouldBeCalled();
    }

    protected function mockGitDeepen(ObjectProphecy $localMachineHelper, string $vcsPath, array $vcsUrls): void
    {
        foreach ($vcsUrls as $vcsUrl) {
            $localMachineHelper->execute([
                'git',
                'fetch',
                '--deepen=50',
                $vcsUrl,
                $vcsPath,
            ], null, Argument::type('string'), false)
                ->willReturn($this->mockProcess()->reveal())->shouldBeCalled();
        }
    }

    protected function mockGitMergeBase(ObjectProphecy $localMachineHelper, string $ancestor, string $descendant, bool $isAncestor): void
    {
        $localMachineHelper->execute([
            'git',
            'merge-base',
            '--is-ancestor',
            $ancestor,
            $descendant,
        ], null, Argument::type('string'), false)
            ->willReturn($this->mockProcess($isAncestor)->reveal())->shouldBeCalled();
    }

    protected function mockLocalGitConfig(ObjectProphecy $localMachineHelper, string $artifactDir, bool $printOutput = true): void
    {
        $process = $this->prophet->prophesize(Process::class);
        $localMachineHelper->execute([
            'git',
            'config',
            '--local',
            'core.excludesFile',
            'false',
        ], Argument::type('callable'), $artifactDir, $printOutput)
            ->willReturn($process->reveal())->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'config',
            '--local',
            'core.fileMode',
            'true',
        ], Argument::type('callable'), $artifactDir, $printOutput)
            ->willReturn($process->reveal())->shouldBeCalled();
    }

    protected function mockComposerInstall(ObjectProphecy $localMachineHelper, mixed $artifactDir, bool $printOutput = true): void
    {
        $localMachineHelper->checkRequiredBinariesExist(['composer'])
            ->shouldBeCalled();
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $localMachineHelper->execute([
            'composer',
            'install',
            '--no-dev',
            '--no-interaction',
            '--optimize-autoloader',
        ], Argument::type('callable'), $artifactDir, $printOutput)
            ->willReturn($process->reveal())->shouldBeCalled();
    }

    protected function mockGitAddCommit(ObjectProphecy $localMachineHelper, string $artifactDir, string $commitHash, bool $printOutput): void
    {
        $process = $this->mockProcess();
        $localMachineHelper->execute([
            'git',
            'add',
            '-A',
        ], Argument::type('callable'), $artifactDir, $printOutput)
            ->willReturn($process->reveal())->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'add',
            '-f',
            'docroot/index.php',
        ], null, $artifactDir, false)
            ->willReturn($process->reveal())->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'add',
            '-f',
            'docroot/autoload.php',
        ], null, $artifactDir, false)
            ->willReturn($process->reveal())->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'add',
            '-f',
            'docroot/core',
        ], null, $artifactDir, false)
            ->willReturn($process->reveal())->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'add',
            '-f',
            'vendor',
        ], null, $artifactDir, false)
            ->willReturn($process->reveal())->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'commit',
            '-m',
            "Automated commit by Acquia CLI (source commit: $commitHash)",
        ], Argument::type('callable'), $artifactDir, $printOutput)
            ->willReturn($process->reveal())->shouldBeCalled();
    }

    protected function mockReadComposerJson(ObjectProphecy $localMachineHelper, string $artifactDir): void
    {
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

    protected function mockGitPush(array $gitUrls, ObjectProphecy $localMachineHelper, string $artifactDir, string $destGitRef, bool $printOutput, array $failingUrls = []): void
    {
        foreach ($gitUrls as $gitUrl) {
            $process = $this->mockProcess(!in_array($gitUrl, $failingUrls, true));
            $localMachineHelper->execute([
                'git',
                'push',
                $gitUrl,
                $destGitRef,
            ], Argument::type('callable'), $artifactDir, $printOutput)
                ->willReturn($process->reveal())->shouldBeCalled();
        }
    }

    protected function mockGitTag(ObjectProphecy $localMachineHelper, string $gitTag, string $artifactDir): void
    {
        $process = $this->mockProcess();
        $localMachineHelper->execute([
            'git',
            'tag',
            $gitTag,
        ], Argument::type('callable'), $artifactDir, true)
            ->willReturn($process->reveal())->shouldBeCalled();
    }
}
