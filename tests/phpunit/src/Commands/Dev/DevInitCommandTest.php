<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Dev;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Dev\DevInitCommand;
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
 * @property \Acquia\Cli\Command\Dev\DevInitCommand $command
 */
class DevInitCommandTest extends PullCommandTestBase
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

        return new DevInitCommand(
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
        $fs = $this->fs;
        $localMachineHelper->execute(['ddev', 'config', '--auto'], Argument::type('callable'), $dir, false)
            ->will(function () use ($process, $fs, $dir) {
                // Ddev config detects the docroot and records it.
                $fs->dumpFile(Path::join($dir, '.ddev', 'config.yaml'), "name: site\ndocroot: web\n");
                return $process->reveal();
            });
        $localMachineHelper->execute(['ddev', 'start', '-y'], Argument::type('callable'), $dir, false, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        // Successful process with a non-bootstrapped status exercises the
        // output check, not just the exit code.
        $drushStatus = $this->mockProcess();
        $drushStatus->getOutput()->willReturn($siteInstalled ? 'Successful' : 'NONE');
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

    public function testDevInitMissingPrerequisites(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->commandExists('git')->willReturn(false);
        $localMachineHelper->commandExists('docker')->willReturn(false);
        $localMachineHelper->commandExists('ddev')->willReturn(false);
        $isMac = PHP_OS_FAMILY === 'Darwin';
        try {
            $this->executeCommand([], [], OutputInterface::VERBOSITY_NORMAL);
            $this->fail('Expected an AcquiaCliException');
        } catch (AcquiaCliException $exception) {
            $message = $exception->getMessage();
            $this->assertStringStartsWith('Some required tools are missing', $message);
            // Each missing tool gets the copy-pasteable remedy for this OS.
            $this->assertStringContainsString($isMac ? 'brew install ddev/ddev/ddev' : 'https://ddev.com/install.sh', $message);
            $this->assertStringContainsString($isMac ? 'brew install --cask docker' : 'https://get.docker.com', $message);
            $this->assertStringContainsString($isMac ? 'xcode-select --install' : 'sudo apt install git', $message);
        }
    }

    public function testDevInitDockerNotRunning(): void
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

    public function testDevInitNotAuthenticatedNonInteractive(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(false);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('This machine is not authenticated with the Cloud Platform');
        $this->executeCommand([], [], OutputInterface::VERBOSITY_NORMAL, false);
    }

    public function testDevInitNoSshKeyNonInteractive(): void
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
     * With no registered SSH key, dev:init generates one, uploads it, and
     * waits for it to become active — no hand-off to other commands.
     */
    public function testDevInitGeneratesAndUploadsSshKey(): void
    {
        $dir = Path::join($this->projectDir, 'site');
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->mockRequest('getAccount');
        $environment = $this->mockRequest('getEnvironment', self::$environmentId);
        $this->mockRequest('getAccountSshKeys');
        $this->mockLocalSshKey($localMachineHelper, 'ssh-rsa KeyNotOnTheCloudPlatform');
        $localMachineHelper->commandExists('ssh-add')->willReturn(false);

        // Key generation.
        $keyPath = Path::join($this->sshDir, 'id_acquia_cli');
        $localMachineHelper->checkRequiredBinariesExist(['ssh-keygen'])
            ->shouldBeCalled();
        $process = $this->mockProcess();
        $localMachineHelper->execute(['ssh-keygen', '-t', 'rsa', '-b', '4096', '-N', '', '-C', 'acli-dev', '-f', $keyPath], null, null, false)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        $sshKeysRequestBody = self::getMockRequestBodyFromSpec('/account/ssh-keys');
        // Trailing newline: the upload must send the trimmed key material.
        $localMachineHelper->readFile($keyPath . '.pub')
            ->willReturn($sshKeysRequestBody['public_key'] . "\n");

        // Upload and installation polling.
        $this->mockRequest('postAccountSshKeys', null, [
            'json' => [
                'label' => preg_replace('/\W/', '', 'acli_dev_' . (gethostname() ?: 'machine')),
                'public_key' => $sshKeysRequestBody['public_key'],
            ],
        ]);
        $localMachineHelper->execute(['git', 'ls-remote', $environment->vcs->url, 'HEAD'], null, null, false, 30, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=accept-new -o BatchMode=yes'])
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        // End the test at the clone: the key flow above is what is under test.
        $localMachineHelper->checkRequiredBinariesExist(['git'])
            ->shouldBeCalled();
        $failedClone = $this->mockProcess(false);
        $localMachineHelper->execute([
            'git',
            'clone',
            $environment->vcs->url,
            $dir,
        ], Argument::type('callable'), null, false, null, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=accept-new'])
            ->willReturn($failedClone->reveal())
            ->shouldBeCalled();
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Failed to clone repository from the Cloud Platform');
        $this->executeCommand([
            '--dir' => $dir,
            'environmentId' => self::$environmentId,
        ], [
            // Generate a new SSH key and upload it to your Acquia account now?
            'y',
        ], OutputInterface::VERBOSITY_NORMAL);
    }

    /**
     * A previously generated key that never made it to the Cloud Platform is
     * reused and uploaded instead of erroring or generating another one.
     */
    public function testDevInitReusesExistingGeneratedKey(): void
    {
        $dir = Path::join($this->projectDir, 'site');
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->mockRequest('getAccount');
        $environment = $this->mockRequest('getEnvironment', self::$environmentId);
        $this->mockRequest('getAccountSshKeys');
        $this->mockLocalSshKey($localMachineHelper, 'ssh-rsa KeyNotOnTheCloudPlatform');
        $localMachineHelper->commandExists('ssh-add')->willReturn(false);

        $keyPath = Path::join($this->sshDir, 'id_acquia_cli');
        $this->fs->dumpFile($keyPath . '.pub', 'ssh-rsa ExistingKey');
        $localMachineHelper->execute(Argument::withEntry(0, 'ssh-keygen'), Argument::cetera())
            ->shouldNotBeCalled();
        $sshKeysRequestBody = self::getMockRequestBodyFromSpec('/account/ssh-keys');
        $localMachineHelper->readFile($keyPath . '.pub')
            ->willReturn($sshKeysRequestBody['public_key']);
        $this->mockRequest('postAccountSshKeys', null, [
            'json' => [
                'label' => preg_replace('/\W/', '', 'acli_dev_' . (gethostname() ?: 'machine')),
                'public_key' => $sshKeysRequestBody['public_key'],
            ],
        ]);
        $process = $this->mockProcess();
        $localMachineHelper->execute(['git', 'ls-remote', $environment->vcs->url, 'HEAD'], null, null, false, 30, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=accept-new -o BatchMode=yes'])
            ->willReturn($process->reveal());
        $localMachineHelper->checkRequiredBinariesExist(['git'])
            ->shouldBeCalled();
        $failedClone = $this->mockProcess(false);
        $localMachineHelper->execute([
            'git',
            'clone',
            $environment->vcs->url,
            $dir,
        ], Argument::type('callable'), null, false, null, ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=accept-new'])
            ->willReturn($failedClone->reveal())
            ->shouldBeCalled();
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Failed to clone repository from the Cloud Platform');
        $this->executeCommand([
            '--dir' => $dir,
            'environmentId' => self::$environmentId,
        ], [
            // Generate a new SSH key and upload it to your Acquia account now?
            'y',
        ], OutputInterface::VERBOSITY_NORMAL);
    }

    /**
     * A non-matching agent key is not good enough: non-interactive mode still
     * fails with the remedy.
     */
    public function testDevInitNoMatchingAgentKeyNonInteractive(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->mockRequest('getAccount');
        $this->mockRequest('getEnvironment', self::$environmentId);
        $this->mockRequest('getAccountSshKeys');
        $this->mockLocalSshKey($localMachineHelper, 'ssh-rsa KeyNotOnTheCloudPlatform');
        $localMachineHelper->commandExists('ssh-add')->willReturn(true);
        $agentList = $this->mockProcess();
        $agentList->getOutput()->willReturn("ssh-rsa AnotherKeyNotOnTheCloudPlatform agent\n");
        $localMachineHelper->execute(['ssh-add', '-L'], null, null, false)
            ->willReturn($agentList->reveal());
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No local SSH key is registered with the Cloud Platform');
        $this->executeCommand([
            'environmentId' => self::$environmentId,
        ], [], OutputInterface::VERBOSITY_NORMAL, false);
    }

    /**
     * Without --dir, dev:init confirms the clone directory interactively with a
     * derived default, and clones into whatever the user answers.
     */
    public function testDevInitPromptsForCloneDirectory(): void
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
    public function testDevInitCloneFailure(): void
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
    public function testDevInitFreshNonInteractive(): void
    {
        $dir = Path::join($this->projectDir, 'site');
        // An existing but empty target directory is fine to clone into.
        $this->fs->mkdir($dir);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $account = $this->mockRequest('getAccount');
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
            Path::join($dir, 'web', 'sites', 'default', 'files'),
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
        $this->assertStringContainsString('✓ Authenticated as ' . $account->mail, $output);
        $this->assertStringContainsString('✓ SSH key id_rsa.pub is registered with the Cloud Platform', $output);
        $this->assertStringContainsString('Your local development environment is ready: https://site.ddev.site', $output);
        $this->assertStringContainsString('What you have:', $output);
        $this->assertStringContainsString('ddev drush uli', $output);
        $this->assertStringContainsString('git push', $output);
        $this->assertStringContainsString('runs the master branch', $output);
        $this->assertStringNotContainsString('Could not run Drush post-install tasks', $output);
        // The project was linked to the Cloud application.
        $this->assertFileExists(Path::join($dir, '.acquia-cli.yml'));
        $this->assertStringContainsString($environment->application->uuid, file_get_contents(Path::join($dir, '.acquia-cli.yml')));
    }

    /**
     * Re-running dev:init on an existing checkout with an installed site skips
     * every completed step instead of redoing it.
     */
    public function testDevInitResumeSkipsCompletedSteps(): void
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
        // configured, composer dependencies installed, and the app linked.
        $this->fs->dumpFile(Path::join($dir, '.git', 'config'), 'url = ' . $environment->vcs->url);
        $localMachineHelper->readFile(Path::join($dir, '.git', 'config'))
            ->willReturn('url = ' . $environment->vcs->url);
        $this->fs->dumpFile(Path::join($dir, '.ddev', 'config.yaml'), 'name: site');
        $this->fs->dumpFile(Path::join($dir, 'composer.json'), '{}');
        $this->fs->mkdir(Path::join($dir, 'vendor'));
        $linkFile = Path::join($dir, '.acquia-cli.yml');
        $this->fs->dumpFile($linkFile, "cloud_app_uuid: sentinel\n");
        $localMachineHelper->execute(['ddev', 'config', '--auto'], Argument::cetera())
            ->shouldNotBeCalled();

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
        // The existing link file is left untouched.
        $this->assertSame("cloud_app_uuid: sentinel\n", file_get_contents($linkFile));
    }

    /**
     * A key that exists only in the SSH agent (with a different comment than
     * the Cloud copy) satisfies the key check.
     */
    public function testDevInitAcceptsSshAgentKey(): void
    {
        $dir = $this->projectDir;
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockPrerequisitesFound($localMachineHelper);
        $this->mockRequest('getAccount');
        $environment = $this->mockRequest('getEnvironment', self::$environmentId);
        $sshKeys = $this->mockRequest('getAccountSshKeys');
        // No local key file matches...
        $this->mockLocalSshKey($localMachineHelper, 'ssh-rsa KeyNotOnTheCloudPlatform');
        // ...but the agent holds the registered key, with its own comment.
        $keyMaterial = implode(' ', array_slice(explode(' ', trim($sshKeys[0]->public_key)), 0, 2));
        $localMachineHelper->commandExists('ssh-add')->willReturn(true)
            ->shouldBeCalled();
        $agentList = $this->mockProcess();
        $agentList->getOutput()->willReturn($keyMaterial . " agent-comment\n");
        $localMachineHelper->execute(['ssh-add', '-L'], null, null, false)
            ->willReturn($agentList->reveal())
            ->shouldBeCalled();
        $this->mockGetFilesystem($localMachineHelper);
        $localMachineHelper->isBrowserAvailable()->willReturn(false);
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

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('✓ An SSH key in your SSH agent is registered with the Cloud Platform', $this->getDisplay());
    }
}
