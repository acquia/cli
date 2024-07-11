<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @property SshKeyCreateCommand $command
 */
class SshKeyCreateCommandTest extends CommandTestBase
{
    protected string $filename = 'id_rsa_acli_test';

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(SshKeyCreateCommand::class);
    }

    /**
     * @return array<mixed>
     */
    public function providerTestCreate(): array
    {
        return [
            [
                true,
        // Args.
                [
                    '--filename' => $this->filename,
                    '--password' => 'acli123',
                ],
                // Inputs.
                [],
            ],
            [
                true,
            // Args.
                [],
            // Inputs.
                [
            // Enter a filename for your new local SSH key:
                    $this->filename,
            // Enter a password for your SSH key:
                    'acli123',
                ],
            ],
            [
                false,
            // Args.
                [],
            // Inputs.
                [
            // Enter a filename for your new local SSH key:
                    $this->filename,
            // Enter a password for your SSH key:
                    'acli123',
                ],
            ],
        ];
    }

    /**
     * @dataProvider providerTestCreate
     * @group brokenProphecy
     */
    public function testCreate(mixed $sshAddSuccess, mixed $args, mixed $inputs): void
    {
        $sshKeyFilepath = Path::join($this->sshDir, '/' . $this->filename);
        $this->fs->remove($sshKeyFilepath);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->getLocalFilepath('~/.passphrase')->willReturn('~/.passphrase');
        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $this->mockAddSshKeyToAgent($localMachineHelper, $fileSystem);
        $this->mockSshAgentList($localMachineHelper, $sshAddSuccess);
        $this->mockGenerateSshKey($localMachineHelper);

        $localMachineHelper->getFilesystem()->willReturn($fileSystem->reveal())->shouldBeCalled();

        $this->executeCommand($args, $inputs);
    }
}
