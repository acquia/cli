<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Acms;

use Acquia\Cli\Command\Acms\AcmsPushCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Tests\Commands\Remote\SshCommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @property \Acquia\Cli\Command\Acms\AcmsPushCommand $command
 */
class AcmsPushCommandTest extends SshCommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(AcmsPushCommand::class);
    }

    public function testPushStreamsConfigToRemoteDrush(): void
    {
        $configDir = $this->mockConfigDir('partial-config', ['node.type.article.yml']);

        $this->mockGetEnvironment();
        [$process, $localMachineHelper] = $this->mockForExecuteCommand();
        $localMachineHelper->checkRequiredBinariesExist(['tar'])
            ->shouldBeCalled();
        $localMachineHelper->checkRequiredBinariesExist(['ssh'])
            ->shouldBeCalled();
        $this->mockPayload($localMachineHelper, $configDir);

        // The payload reaches config:import-stream on stdin, and that is the
        // only thing this command asks the environment to do: maintenance mode
        // and content type cleanup live on the far side.
        $localMachineHelper
            ->execute(self::sshDrushCommand('config:import-stream'), Argument::type('callable'), null, true, null, null, true, 'TARBALL')
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        $this->command->localMachineHelper = $localMachineHelper->reveal();
        $this->command->sshHelper = new SshHelper($this->output, $localMachineHelper->reveal(), $this->logger);
        $this->executeCommand(['config_dir' => $configDir], self::inputChooseEnvironment());

        $this->assertStringContainsString('Pushing config from', $this->getDisplay());
        $this->assertSame(0, $this->getStatusCode());
    }

    public function testPushReturnsTheRemoteExitCode(): void
    {
        $configDir = $this->mockConfigDir('partial-config', ['node.type.article.yml']);

        $this->mockGetEnvironment();
        [, $localMachineHelper] = $this->mockForExecuteCommand();
        $localMachineHelper->checkRequiredBinariesExist(['tar'])->shouldBeCalled();
        $localMachineHelper->checkRequiredBinariesExist(['ssh'])->shouldBeCalled();
        $this->mockPayload($localMachineHelper, $configDir);

        // A refused or failed import is reported by the remote command; this
        // one only has to pass the failure back to the caller.
        $failedProcess = $this->prophet->prophesize(Process::class);
        $failedProcess->getExitCode()->willReturn(1);
        $failedProcess->isSuccessful()->willReturn(false);
        $localMachineHelper
            ->execute(self::sshDrushCommand('config:import-stream'), Argument::type('callable'), null, true, null, null, true, 'TARBALL')
            ->willReturn($failedProcess->reveal())
            ->shouldBeCalled();

        $this->command->localMachineHelper = $localMachineHelper->reveal();
        $this->command->sshHelper = new SshHelper($this->output, $localMachineHelper->reveal(), $this->logger);
        $this->executeCommand(['config_dir' => $configDir], self::inputChooseEnvironment());

        $this->assertSame(1, $this->getStatusCode());
    }

    public function testPushLocalStreamsConfigToDdev(): void
    {
        $configDir = $this->mockConfigDir('local-config', ['node.type.article.yml']);
        mkdir(Path::join($this->projectDir, '.ddev'), 0777, true);
        touch(Path::join($this->projectDir, '.ddev', 'config.yaml'));

        $localMachineHelper = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelper->checkRequiredBinariesExist(['tar'])->shouldBeCalled();
        $localMachineHelper->checkRequiredBinariesExist(['ddev'])->shouldBeCalled();
        $this->mockPayload($localMachineHelper, $configDir);

        $drushProcess = $this->prophet->prophesize(Process::class);
        $drushProcess->getExitCode()->willReturn(0);
        $localMachineHelper
            ->execute(['ddev', 'drush', 'config:import-stream'], null, $this->projectDir, true, null, null, true, 'TARBALL')
            ->willReturn($drushProcess->reveal())
            ->shouldBeCalled();

        $this->command->localMachineHelper = $localMachineHelper->reveal();
        $this->executeCommand(['config_dir' => $configDir, '--local' => true]);

        $this->assertStringContainsString('to the DDEV site at ' . $this->projectDir, $this->getDisplay());
    }

    public function testPushLocalFailsOutsideADdevProject(): void
    {
        $configDir = $this->mockConfigDir('stray-config', ['node.type.article.yml']);

        $localMachineHelper = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelper->checkRequiredBinariesExist(['tar'])->shouldBeCalled();
        $this->command->localMachineHelper = $localMachineHelper->reveal();

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Could not find a DDEV project');

        $this->executeCommand(['config_dir' => $configDir, '--local' => true]);
    }

    public function testPushFailsWhenConfigDirIsMissing(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('does not exist');

        $this->executeCommand(['config_dir' => Path::join($this->projectDir, 'nope')]);
    }

    /**
     * @param array<int, string> $files
     */
    private function mockConfigDir(string $name, array $files): string
    {
        $configDir = Path::join($this->projectDir, $name);
        mkdir($configDir, 0777, true);
        foreach ($files as $file) {
            touch(Path::join($configDir, $file));
        }

        return $configDir;
    }

    /**
     * The payload is built locally, then handed to drush as stdin.
     *
     * @param \Prophecy\Prophecy\ObjectProphecy<\Acquia\Cli\Helpers\LocalMachineHelper> $localMachineHelper
     */
    private function mockPayload(ObjectProphecy $localMachineHelper, string $configDir): void
    {
        $tarProcess = $this->prophet->prophesize(Process::class);
        $tarProcess->isSuccessful()->willReturn(true);
        $tarProcess->getOutput()->willReturn('TARBALL');
        $localMachineHelper
            ->execute(['tar', '-cz', '-C', $configDir, '.'], null, null, false)
            ->willReturn($tarProcess->reveal())
            ->shouldBeCalled();
    }

    /**
     * @return array<int, string>
     */
    private static function sshDrushCommand(string $drushArguments): array
    {
        return [
            'ssh',
            'site.dev@sitedev.ssh.hosted.acquia-sites.com',
            '-t',
            '-o StrictHostKeyChecking=accept-new',
            '-o AddressFamily inet',
            '-o LogLevel=ERROR',
            'cd /var/www/html/site.dev/docroot; ',
            'drush',
            '--uri=http://sitedev.hosted.acquia-sites.com ' . $drushArguments,
        ];
    }
}
