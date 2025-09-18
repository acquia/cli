<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyListCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property SshKeyListCommand $command
 */
class SshKeyListCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(SshKeyListCommand::class);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->setupFsFixture();
        $this->command = $this->createCommand();
    }

    public function testList(): void
    {

        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();
        $mockRequestArgs = self::getMockRequestBodyFromSpec('/account/ssh-keys');
        $tempFileName = $this->createLocalSshKey($mockRequestArgs['public_key']);
        $baseFilename = basename($tempFileName);
        $this->executeCommand();

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Local filename', $output);
        $this->assertStringContainsString('Cloud Platform label', $output);
        $this->assertStringContainsString('Fingerprint', $output);
        $this->assertStringContainsString($baseFilename, $output);
        $this->assertStringContainsString($mockBody->_embedded->items[0]->label, $output);
        $this->assertStringContainsString($mockBody->_embedded->items[1]->label, $output);
        $this->assertStringContainsString($mockBody->_embedded->items[2]->label, $output);
    }

    public function testListWithMultipleLocalKeys(): void
    {
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();

        // Create multiple local SSH keys with different content to test findLocalSshKeys returns all.
        $key1Content = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQClocal1 user1@local1';
        $key2Content = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQClocal2 user2@local2';
        $key3Content = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAILocalKey3 user3@local3';

        $tempFileName1 = $this->createLocalSshKey($key1Content);
        $tempFileName2 = $this->createLocalSshKey($key2Content);
        $tempFileName3 = $this->createLocalSshKey($key3Content);

        $baseFilename1 = basename($tempFileName1);
        $baseFilename2 = basename($tempFileName2);
        $baseFilename3 = basename($tempFileName3);

        $this->executeCommand();

        // Assert all three local keys are displayed (this would fail if findLocalSshKeys only returned first key)
        $output = $this->getDisplay();
        $this->assertStringContainsString($baseFilename1, $output);
        $this->assertStringContainsString($baseFilename2, $output);
        $this->assertStringContainsString($baseFilename3, $output);
        $this->assertStringContainsString('Local keys with no matching Cloud Platform keys', $output);
    }

    public function testListWithWhitespaceInSshKeys(): void
    {
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();

        // Create SSH keys with leading/trailing whitespace that should be valid after trimming.
        $keyWithNewline = "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCwhitespace user@whitespace\n";
        $keyWithSpaces = "  ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIWhitespaceKey user@spaces  ";
        $keyWithTabs = "\tssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCtabkey user@tabs\t";

        $tempFileName1 = $this->createLocalSshKey($keyWithNewline);
        $tempFileName2 = $this->createLocalSshKey($keyWithSpaces);
        $tempFileName3 = $this->createLocalSshKey($keyWithTabs);

        $baseFilename1 = basename($tempFileName1);
        $baseFilename2 = basename($tempFileName2);
        $baseFilename3 = basename($tempFileName3);

        $this->executeCommand();

        // Assert all whitespace keys are displayed (this would fail if trim() was removed)
        $output = $this->getDisplay();
        $this->assertStringContainsString($baseFilename1, $output);
        $this->assertStringContainsString($baseFilename2, $output);
        $this->assertStringContainsString($baseFilename3, $output);
        $this->assertStringContainsString('Local keys with no matching Cloud Platform keys', $output);
    }
}
