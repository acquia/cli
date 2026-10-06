<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Remote\SshCommand;
use Acquia\Cli\Helpers\SshHelper;
use Prophecy\Argument;

/**
 * @property SshCommand $command
 */
class SshCommandTest extends SshCommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(SshCommand::class);
    }

    public function testRemoteSshCommand(): void
    {
        $this->mockGetEnvironment();
        [$process, $localMachineHelper] = $this->mockForExecuteCommand();
        $localMachineHelper->checkRequiredBinariesExist(['ssh'])
            ->shouldBeCalled();
        $sshCommand = [
            'ssh',
            'site.dev@sitedev.ssh.hosted.acquia-sites.com',
            '-t',
            '-o StrictHostKeyChecking=accept-new',
            '-o AddressFamily inet',
            '-o LogLevel=ERROR',
            'cd /var/www/html/site.dev; exec $SHELL -l',
        ];
        $localMachineHelper
            ->execute($sshCommand, Argument::type('callable'), null, true, null, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        $this->command->sshHelper = new SshHelper($this->output, $localMachineHelper->reveal(), $this->logger);
        $this->executeCommand(['ssh_command' => []], self::inputChooseEnvironment());

        $this->getDisplay();
    }

    public function testRemoteSshCommandAllowsProductionEnvironment(): void
    {
        $this->mockGetEnvironment();
        [$process, $localMachineHelper] = $this->mockForExecuteCommand();
        $localMachineHelper->checkRequiredBinariesExist(['ssh'])
            ->shouldBeCalled();
        $sshCommand = [
            'ssh',
            'site.prod@siteprod.ssh.hosted.acquia-sites.com',
            '-t',
            '-o StrictHostKeyChecking=accept-new',
            '-o AddressFamily inet',
            '-o LogLevel=ERROR',
            'cd /var/www/html/site.prod; exec $SHELL -l',
        ];
        $localMachineHelper
            ->execute($sshCommand, Argument::type('callable'), null, true, null, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        $this->command->sshHelper = new SshHelper($this->output, $localMachineHelper->reveal(), $this->logger);
        $this->executeCommand(['ssh_command' => []], [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            self::$INPUT_DEFAULT_CHOICE,
            // Would you like to link the project at ... ?
            'n',
            // Choose a Cloud Platform environment (index 1 = prod):
            1,
        ]);

        $this->getDisplay();
    }

    public function testRemoteSshCommandWithEnvUuid(): void
    {
        $this->mockRequest('getEnvironment', '24-a47ac10b-58cc-4372-a567-0e02b2c3d470');
        [$process, $localMachineHelper] = $this->mockForExecuteCommand();
        $localMachineHelper->checkRequiredBinariesExist(['ssh'])
            ->shouldBeCalled();
        $sshCommand = [
            'ssh',
            'site.dev@sitedev.ssh.hosted.acquia-sites.com',
            '-t',
            '-o StrictHostKeyChecking=accept-new',
            '-o AddressFamily inet',
            '-o LogLevel=ERROR',
            'cd /var/www/html/site.dev; exec $SHELL -l',
        ];
        $localMachineHelper
            ->execute($sshCommand, Argument::type('callable'), null, true, null, null)
            ->willReturn($process->reveal())
            ->shouldBeCalled();

        $this->command->sshHelper = new SshHelper($this->output, $localMachineHelper->reveal(), $this->logger);
        $this->executeCommand(['environmentId' => '24-a47ac10b-58cc-4372-a567-0e02b2c3d470']);

        $this->getDisplay();
    }
}
