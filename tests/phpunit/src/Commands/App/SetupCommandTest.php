<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\SetupCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use Acquia\Cli\Tests\Commands\Pull\PullCommandTestBase;
use ArrayIterator;
use GuzzleHttp\Client;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @property \Acquia\Cli\Command\App\SetupCommand $command
 */
class SetupCommandTest extends PullCommandTestBase
{
    private static string $environmentId = '24-a47ac10b-58cc-4372-a567-0e02b2c3d470';

    public function setUp(): void
    {
        parent::setUp();
        IdeHelper::unsetCloudIdeEnvVars();
    }

    protected function createCommand(): CommandBase
    {
        $this->httpClientProphecy = $this->prophet->prophesize(Client::class);

        return new SetupCommand(
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

    private function mockPrerequisitesFound(ObjectProphecy $localMachineHelper): void
    {
        foreach (['git', 'docker', 'ddev'] as $binary) {
            $localMachineHelper->commandExists($binary)
                ->willReturn(true)
                ->shouldBeCalled();
        }
        $process = $this->mockProcess();
        $localMachineHelper->execute(['docker', 'info'], null, null, false, 30)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    /**
     * Mock a local SSH key whose contents match the given public key.
     */
    private function mockLocalSshKey(ObjectProphecy $localMachineHelper, string $publicKey): void
    {
        $finder = $this->prophet->prophesize(Finder::class);
        $finder->files()->willReturn($finder);
        $finder->in(Argument::type('string'))->willReturn($finder);
        $finder->name('*.pub')->willReturn($finder);
        $finder->ignoreUnreadableDirs()->willReturn($finder);
        $file = $this->prophet->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($publicKey);
        $file->getFilename()->willReturn('id_rsa.pub');
        $finder->getIterator()->willReturn(new ArrayIterator([$file->reveal()]));
        $localMachineHelper->getFinder()->willReturn($finder);
    }

    private function mockDdev(ObjectProphecy $localMachineHelper, string $dir, bool $siteInstalled): void
    {
        $process = $this->mockProcess();
        $localMachineHelper->execute(['ddev', 'config', '--auto'], Argument::type('callable'), $dir, false)
            ->willReturn($process->reveal());
        $localMachineHelper->execute(['ddev', 'start', '-y'], Argument::type('callable'), $dir, false, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $drushStatus = $this->mockProcess($siteInstalled);
        $drushStatus->getOutput()->willReturn($siteInstalled ? 'Successful' : '');
        $localMachineHelper->execute(['ddev', 'drush', 'status', '--field=bootstrap'], null, $dir, false)
            ->willReturn($drushStatus->reveal())
            ->shouldBeCalled();
        $describe = $this->mockProcess();
        $describe->getOutput()->willReturn(json_encode(['raw' => ['primary_url' => 'https://site.ddev.site']]));
        $localMachineHelper->execute(['ddev', 'describe', '-j'], null, $dir, false)
            ->willReturn($describe->reveal())
            ->shouldBeCalled();
        $response = $this->prophet->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);
        $this->httpClientProphecy->request('GET', 'https://site.ddev.site', Argument::any())
            ->willReturn($response->reveal())
            ->shouldBeCalled();
    }

    public function testSetupMissingPrerequisites(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->commandExists('git')->willReturn(true);
        $localMachineHelper->commandExists('docker')->willReturn(false);
        $localMachineHelper->commandExists('ddev')->willReturn(false);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Some required tools are missing');
        $this->executeCommand([], [], OutputInterface::VERBOSITY_NORMAL);
    }

    public function testSetupDockerNotRunning(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        foreach (['git', 'docker', 'ddev'] as $binary) {
            $localMachineHelper->commandExists($binary)->willReturn(true);
        }
        $process = $this->mockProcess(false);
        $localMachineHelper->execute(['docker', 'info'], null, null, false, 30)
            ->willReturn($process->reveal());
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Docker is installed but not running');
        $this->executeCommand([], [], OutputInterface::VERBOSITY_NORMAL);
    }

    public function testSetupNotAuthenticatedNonInteractive(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(false);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('This machine is not authenticated with the Cloud Platform');
        $this->executeCommand([], [], OutputInterface::VERBOSITY_NORMAL, false);
    }

    public function testSetupNoSshKeyNonInteractive(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->mockRequest('getAccount');
        $this->mockRequest('getEnvironment', self::$environmentId);
        $this->mockRequest('getAccountSshKeys');
        $this->mockLocalSshKey($localMachineHelper, 'ssh-rsa KeyNotOnTheCloudPlatform');
        $localMachineHelper->commandExists('ssh-add')->willReturn(false);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No local SSH key is registered with the Cloud Platform');
        $this->executeCommand([
            'environmentId' => self::$environmentId,
        ], [], OutputInterface::VERBOSITY_NORMAL, false);
    }

    /**
     * Without --dir, setup confirms the clone directory interactively with a
     * derived default, and clones into whatever the user answers.
     */
    public function testSetupPromptsForCloneDirectory(): void
    {
        $answeredDir = Path::join($this->projectDir, 'my-custom-dir');
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->mockRequest('getAccount');
        $environment = $this->mockRequest('getEnvironment', self::$environmentId);
        $sshKeys = $this->mockRequest('getAccountSshKeys');
        $this->mockLocalSshKey($localMachineHelper, $sshKeys[0]->public_key);
        $localMachineHelper->readFile(Argument::type('string'))->willReturn('');
        $localMachineHelper->checkRequiredBinariesExist(['git'])
            ->shouldBeCalled();
        $process = $this->mockProcess(false);
        $localMachineHelper->execute([
            'git',
            'clone',
            $environment->vcs->url,
            $answeredDir,
        ], Argument::type('callable'), null, false, null, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=accept-new'])
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Failed to clone repository from the Cloud Platform');
        $this->executeCommand([
            'environmentId' => self::$environmentId,
        ], [
            // Where should the code be cloned?
            $answeredDir,
        ], OutputInterface::VERBOSITY_NORMAL);
    }

    /**
     * A failed clone must throw the clone error, not attempt the branch
     * checkout in a directory that does not exist.
     */
    public function testSetupCloneFailure(): void
    {
        $dir = Path::join($this->projectDir, 'site');
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->mockRequest('getAccount');
        $environment = $this->mockRequest('getEnvironment', self::$environmentId);
        $sshKeys = $this->mockRequest('getAccountSshKeys');
        $this->mockLocalSshKey($localMachineHelper, $sshKeys[0]->public_key);
        $localMachineHelper->checkRequiredBinariesExist(['git'])
            ->shouldBeCalled();
        $process = $this->mockProcess(false);
        $localMachineHelper->execute([
            'git',
            'clone',
            $environment->vcs->url,
            $dir,
        ], Argument::type('callable'), null, false, null, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=accept-new'])
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'checkout',
            $environment->vcs->path,
        ], Argument::cetera())
            ->shouldNotBeCalled();
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Failed to clone repository from the Cloud Platform');
        $this->executeCommand([
            '--dir' => $dir,
            'environmentId' => self::$environmentId,
        ], [], OutputInterface::VERBOSITY_NORMAL, false);
    }

    /**
     * From nothing to a working site, non-interactively: clone, configure
     * ddev, start it, import the database, sync files.
     */
    public function testSetupFreshNonInteractive(): void
    {
        $dir = Path::join($this->projectDir, 'site');
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->mockRequest('getAccount');
        $environment = $this->mockRequest('getEnvironment', self::$environmentId);
        $sshKeys = $this->mockRequest('getAccountSshKeys');
        $this->mockLocalSshKey($localMachineHelper, $sshKeys[0]->public_key);
        $this->mockGetFilesystem($localMachineHelper);

        // Clone.
        $localMachineHelper->checkRequiredBinariesExist(['git'])
            ->shouldBeCalled();
        $process = $this->mockProcess();
        $localMachineHelper->execute([
            'git',
            'clone',
            $environment->vcs->url,
            $dir,
        ], Argument::type('callable'), null, false, null, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=accept-new'])
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $localMachineHelper->execute([
            'git',
            'checkout',
            $environment->vcs->path,
        ], Argument::type('callable'), $dir, false)
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        $this->mockDdev($localMachineHelper, $dir, false);

        // Database download and import.
        $sshHelper = $this->mockSshHelper();
        $this->mockListSites($sshHelper);
        $this->command->sshHelper = $sshHelper->reveal();
        $this->mockGetBackup($environment);
        $dumpPath = Path::join(sys_get_temp_dir(), 'dev-my_db-my_dbdev-2012-05-15T12:00:00Z.sql.gz');
        $localMachineHelper->checkRequiredBinariesExist(['gunzip'])
            ->shouldBeCalled();
        $localMachineHelper->executeFromCmd('bash -o pipefail -c "gunzip -c \"$DUMP_FILEPATH\" | ddev import-db"', Argument::type('callable'), $dir, false, null, ['DUMP_FILEPATH' => $dumpPath])
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        // Files.
        $localMachineHelper->checkRequiredBinariesExist(['rsync'])
            ->shouldBeCalled();
        $localMachineHelper->execute([
            'rsync',
            '-avPhze',
            'ssh -o StrictHostKeyChecking=accept-new',
            $environment->ssh_url . ':/mnt/files/site.dev/sites/default/files/',
            $dir . '/docroot/sites/default/files',
        ], Argument::type('callable'), null, false)
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        // Drush cache rebuild and sanitization.
        $localMachineHelper->execute(['ddev', 'drush', 'cache:rebuild', '--yes'], Argument::type('callable'), $dir, false, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $localMachineHelper->execute(['ddev', 'drush', 'sql:sanitize', '--yes'], Argument::type('callable'), $dir, false, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        $this->executeCommand([
            '--dir' => $dir,
            'environmentId' => self::$environmentId,
        ], [], OutputInterface::VERBOSITY_NORMAL, false);

        $output = $this->getDisplay();
        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('✓ Found git, docker, and ddev', $output);
        $this->assertStringContainsString('✓ Authenticated as', $output);
        $this->assertStringContainsString('✓ SSH key id_rsa.pub is registered with the Cloud Platform', $output);
        $this->assertStringContainsString('Your local development environment is ready: https://site.ddev.site', $output);
        $this->assertStringContainsString('ddev drush uli', $output);
        $this->assertStringContainsString('git push', $output);
        $this->assertStringContainsString('runs the master branch', $output);
        // The project was linked to the Cloud application.
        $this->assertFileExists(Path::join($dir, '.acquia-cli.yml'));
        $this->assertStringContainsString($environment->application->uuid, file_get_contents(Path::join($dir, '.acquia-cli.yml')));
    }

    /**
     * Re-running setup on an existing checkout with an installed site skips
     * every completed step instead of redoing it.
     */
    public function testSetupResumeSkipsCompletedSteps(): void
    {
        $dir = $this->projectDir;
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->mockRequest('getAccount');
        $environment = $this->mockRequest('getEnvironment', self::$environmentId);
        $sshKeys = $this->mockRequest('getAccountSshKeys');
        $this->mockLocalSshKey($localMachineHelper, $sshKeys[0]->public_key);
        $this->mockGetFilesystem($localMachineHelper);
        $localMachineHelper->isBrowserAvailable()->willReturn(false);

        // An existing checkout of this application with ddev already
        // configured and composer dependencies already installed.
        $this->fs->dumpFile(Path::join($dir, '.git', 'config'), 'url = ' . $environment->vcs->url);
        $localMachineHelper->readFile(Path::join($dir, '.git', 'config'))
            ->willReturn('url = ' . $environment->vcs->url);
        $this->fs->dumpFile(Path::join($dir, '.ddev', 'config.yaml'), 'name: site');
        $this->fs->dumpFile(Path::join($dir, 'composer.json'), '{}');
        $this->fs->mkdir(Path::join($dir, 'vendor'));

        $this->mockDdev($localMachineHelper, $dir, true);

        $this->executeCommand([
            '--dir' => $dir,
            'environmentId' => self::$environmentId,
        ], [], OutputInterface::VERBOSITY_NORMAL);

        $output = $this->getDisplay();
        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('✓ Code already cloned to ' . $dir, $output);
        $this->assertStringContainsString('✓ ddev is already configured', $output);
        $this->assertStringContainsString('✓ Composer dependencies already installed', $output);
        $this->assertStringContainsString('✓ Site database already present', $output);
        $this->assertStringContainsString('Your local development environment is ready: https://site.ddev.site', $output);
    }
}
