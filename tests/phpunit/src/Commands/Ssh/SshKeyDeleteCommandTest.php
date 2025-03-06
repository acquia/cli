<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property SshKeyDeleteCommand $command
 */
class SshKeyDeleteCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(SshKeyDeleteCommand::class);
    }

    /**
     * @throws \Exception
     */
    public function testDelete(): void
    {
        $sshKeyListResponse = $this->mockListSshKeysRequest();
        $this->mockRequest('deleteAccountSshKey', $sshKeyListResponse[self::$INPUT_DEFAULT_CHOICE]->uuid, null, 'Removed key');

        $inputs = [
            // Choose key.
            self::$INPUT_DEFAULT_CHOICE,
            // Do you also want to delete the corresponding local key files?
            'n',
        ];
        $this->executeCommand([], $inputs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Choose an SSH key to delete from the Cloud Platform', $output);
        $this->assertStringContainsString($sshKeyListResponse[self::$INPUT_DEFAULT_CHOICE]->label, $output);

        // Delete key when uuid and label are provided.
        $this->executeCommand([
            '--cloud-key-uuid' => $sshKeyListResponse[self::$INPUT_DEFAULT_CHOICE]->uuid,
            '--label' => $sshKeyListResponse[self::$INPUT_DEFAULT_CHOICE]->label,
        ]);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Successfully deleted SSH key', $output);
    }
}
