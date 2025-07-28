<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\Pull\PullCodeCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use GuzzleHttp\Client;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @property \Acquia\Cli\Command\Pull\PullCodeCommand $command
 */
class PullCodeCommandTest extends PullCommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $this->httpClientProphecy = $this->prophet->prophesize(Client::class);

        return new PullCodeCommand(
            $this->localMachineHelper,
            $this->datastoreCloud,
            $this->datastoreAcli,
            $this->cloudCredentials,
            $this->telemetryHelper,
            $this->acliRepoRoot,
            $this->clientServiceProphecy->reveal(),
            $this->sshHelper,
            $this->sshDir,
            $this->logger,
            $this->selfUpdateManager,
            $this->httpClientProphecy->reveal()
        );
    }

    public function testCloneRepo(): void
    {
        // Unset repo root. Mimics failing to find local git repo. Command must be re-created
        // to re-inject the parameter into the command.
        $this->acliRepoRoot = '';
        $this->command = $this->createCommand();
        // Client responses.
        $siteInstance = $this->mockGetSiteInstance();
        $localMachineHelper = $this->mockReadIdePhpVersion();
        $process = $this->mockProcess();
        $dir = Path::join($this->vfsRoot->url(), 'empty-dir');
        mkdir($dir);
        $localMachineHelper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
        $this->mockExecuteGitClone($localMachineHelper, $siteInstance, $process, $dir);
        $this->mockExecuteGitCheckout($localMachineHelper, $siteInstance->environment->codebase->vcs_url, $dir, $process);
        $localMachineHelper->getFinder()->willReturn(new Finder());

        $inputs = [
            // Would you like to clone a project into the current directory?
            'y',
            // Select a Cloud Platform application:
            self::$INPUT_DEFAULT_CHOICE,
            // Would you like to link the project at ... ?
            'n',
            // Choose an Acquia environment:
            self::$INPUT_DEFAULT_CHOICE,
        ];
        $this->executeCommand([
            '--dir' => $dir,
            '--no-scripts' => true,
            'siteInstanceId' => $siteInstance->site_id . "." . $siteInstance->environment_id,
        ], $inputs);
    }

    public function testPullCode(): void
    {
        $siteInstance = $this->mockGetSiteInstance();
        $this->createMockGitConfigFile();

        $localMachineHelper = $this->mockReadIdePhpVersion();
        $localMachineHelper->checkRequiredBinariesExist(["git"])
            ->shouldBeCalled();
        $finder = $this->mockFinder();
        $localMachineHelper->getFinder()->willReturn($finder->reveal());

        $process = $this->mockProcess();
        $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $this->projectDir, $siteInstance->environment->codebase->vcs_url);
        $this->mockExecuteGitStatus(false, $localMachineHelper, $this->projectDir);

        $this->executeCommand([
            '--no-scripts' => true,
            'siteInstanceId' => $siteInstance->site_id . '.' . $siteInstance->environment_id,
        ], self::inputChooseEnvironment());

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    }

    public function testWithScripts(): void
    {
        touch(Path::join($this->projectDir, 'composer.json'));
        $siteInstance = $this->mockGetSiteInstance();
        $this->createMockGitConfigFile();

        $localMachineHelper = $this->mockReadIdePhpVersion();
        $localMachineHelper->checkRequiredBinariesExist(["git"])
            ->shouldBeCalled();
        $finder = $this->mockFinder();
        $localMachineHelper->getFinder()->willReturn($finder->reveal());

        $process = $this->mockProcess();
        $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $this->projectDir, $siteInstance->environment->codebase->vcs_url);
        $this->mockExecuteGitStatus(false, $localMachineHelper, $this->projectDir);
        $process = $this->mockProcess();
        $this->mockExecuteComposerExists($localMachineHelper);
        $this->mockExecuteComposerInstall($localMachineHelper, $process);
        $this->mockExecuteDrushExists($localMachineHelper);
        $this->mockExecuteDrushStatus($localMachineHelper, $this->projectDir);
        $this->mockExecuteDrushCacheRebuild($localMachineHelper, $process);

        $this->executeCommand(['siteInstanceId' => $siteInstance->site_id . "." . $siteInstance->environment_id], self::inputChooseEnvironment());

        $output = $this->getDisplay();

        $this->assertStringContainsString('Select a Cloud Platform application:', $output);
        $this->assertStringContainsString('[0] Sample application 1', $output);
        $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
        $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    }

    public function testNoComposerJson(): void
    {
        $siteInstance = $this->mockGetSiteInstance();
        $this->createMockGitConfigFile();

        $localMachineHelper = $this->mockReadIdePhpVersion();
        $localMachineHelper->checkRequiredBinariesExist(["git"])
            ->shouldBeCalled();
        $finder = $this->mockFinder();
        $localMachineHelper->getFinder()->willReturn($finder->reveal());

        $process = $this->mockProcess();
        $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $this->projectDir, $siteInstance->environment->codebase->vcs_url);
        $this->mockExecuteGitStatus(false, $localMachineHelper, $this->projectDir);
        $process = $this->mockProcess();
        $this->mockExecuteDrushExists($localMachineHelper);
        $this->mockExecuteDrushStatus($localMachineHelper, $this->projectDir);
        $this->mockExecuteDrushCacheRebuild($localMachineHelper, $process);

        $this->executeCommand(['siteInstanceId' => $siteInstance->site_id . "." . $siteInstance->environment_id], self::inputChooseEnvironment());

        $output = $this->getDisplay();
        $this->assertStringContainsString('composer.json file not found. Skipping composer install.', $output);
    }

    public function testNoComposer(): void
    {
        touch(Path::join($this->projectDir, 'composer.json'));
        $siteInstance = $this->mockGetSiteInstance();
        $this->createMockGitConfigFile();

        $localMachineHelper = $this->mockReadIdePhpVersion();
        $localMachineHelper->checkRequiredBinariesExist(["git"])
            ->shouldBeCalled();
        $finder = $this->mockFinder();
        $localMachineHelper->getFinder()->willReturn($finder->reveal());

        $process = $this->mockProcess();
        $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $this->projectDir, $siteInstance->environment->codebase->vcs_url);
        $this->mockExecuteGitStatus(false, $localMachineHelper, $this->projectDir);
        $process = $this->mockProcess();
        $localMachineHelper
            ->commandExists('composer')
            ->willReturn(false)
            ->shouldBeCalled();
        $this->mockExecuteDrushExists($localMachineHelper);
        $this->mockExecuteDrushStatus($localMachineHelper, $this->projectDir);
        $this->mockExecuteDrushCacheRebuild($localMachineHelper, $process);

        $this->executeCommand(['siteInstanceId' => $siteInstance->site_id . '.' . $siteInstance->environment_id], self::inputChooseEnvironment());

        $output = $this->getDisplay();

        $this->assertStringContainsString('Composer not found. Skipping composer install.', $output);
    }

    public function testWithVendorDir(): void
    {
        touch(Path::join($this->projectDir, 'composer.json'));
        touch(Path::join($this->projectDir, 'vendor'));
        $siteInstance = $this->mockGetSiteInstance();
        $this->createMockGitConfigFile();

        $localMachineHelper = $this->mockReadIdePhpVersion();
        $localMachineHelper->checkRequiredBinariesExist(["git"])
            ->shouldBeCalled();
        $finder = $this->mockFinder();
        $localMachineHelper->getFinder()->willReturn($finder->reveal());

        $process = $this->mockProcess();
        $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $this->projectDir, $siteInstance->environment->codebase->vcs_url);
        $this->mockExecuteGitStatus(false, $localMachineHelper, $this->projectDir);
        $process = $this->mockProcess();
        $this->mockExecuteComposerExists($localMachineHelper);
        $this->mockExecuteDrushExists($localMachineHelper);
        $this->mockExecuteDrushStatus($localMachineHelper, $this->projectDir);
        $this->mockExecuteDrushCacheRebuild($localMachineHelper, $process);

        $this->executeCommand(['siteInstanceId' => $siteInstance->site_id . '.' . $siteInstance->environment_id], self::inputChooseEnvironment());

        $output = $this->getDisplay();

        $this->assertStringContainsString('Composer dependencies already installed. Skipping composer install.', $output);
    }

    /**
     * @return string[][]
     */
    public static function providerTestMatchPhpVersion(): array
    {
        return [
            ['7.1'],
            ['7.2'],
            [''],
        ];
    }

    /**
     * @dataProvider providerTestMatchPhpVersion
     */
    public function testMatchPhpVersion(string $phpVersion): void
    {
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

        $process = $this->mockProcess();
        $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $dir, 'master');
        $this->mockExecuteGitStatus(false, $localMachineHelper, $dir);

        $siteInstanceResponse = $this->getMockSiteInstanceResponse();
        $environmentResponse = $this->getMockCodeBaseEnvironment();
        $siteResponse = $this->getMockSite();
        $codebaseResponse = $this->getMockCodeBase();
        $environmentResponse->codebase = $codebaseResponse;
        $siteInstanceResponse->environment = $environmentResponse;
        $siteInstanceResponse->site = $siteResponse;

        $siteInstanceResponse->environment->properties['version'] = '7.1';
        $environmentResponse = $siteInstanceResponse->environment;
        $this->clientProphecy->request(
            'get',
            "/v3/environments/" . $environmentResponse->id
        )
            ->willReturn($environmentResponse)
            ->shouldBeCalled();
        $this->executeCommand([
            '--dir' => $dir,
            '--no-scripts' => true,
            // @todo Execute ONLY match php aspect, not the code pull.
            'siteInstanceId' => $siteInstanceResponse->site_id . '.' . $environmentResponse->id,
        ], [
            // Choose an Acquia environment:
            self::$INPUT_DEFAULT_CHOICE,
            // Would you like to change the PHP version on this IDE to match the PHP version on ... ?
            'n',
        ]);

        $output = $this->getDisplay();
        IdeHelper::unsetCloudIdeEnvVars();
        $message = "Would you like to change the PHP version on this IDE to match the PHP version on the $environmentResponse->label ({$environmentResponse->configuration->php->version}) environment?";
        if ($phpVersion === '7.1') {
            $this->assertStringNotContainsString($message, $output);
        } else {
            $this->assertStringContainsString($message, $output);
        }
    }

    protected function mockExecuteGitClone(
        ObjectProphecy $localMachineHelper,
        object $siteInstanceResponse,
        ObjectProphecy $process,
        mixed $dir
    ): void {
        $command = [
            'git',
            'clone',
            $siteInstanceResponse->environment->codebase->vcs_url,
            $dir,
        ];
        $localMachineHelper->execute($command, Argument::type('callable'), null, true, null, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=no'])
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }
}
