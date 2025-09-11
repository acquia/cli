<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Endpoints\SshKeys;
use AcquiaCloudApi\Response\OperationResponse;
use AcquiaCloudApi\Response\SshKeyResponse;

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
    public function testDeleteWithoutUuid(): void
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
    }

    /**
     * @throws \Exception
     * @throws Exception
     */
    public function testDeleteWithUuid(): void
    {
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');

        // Mock the SshKeys->get method to return the predefined response.
        $sshKeysMock = $this->createMock(SshKeys::class);
        $sshKeysMock->method('get')
            ->willReturn(new SshKeyResponse($mockBody->{'_embedded'}->items[0]));

        // Mock the request method for the specific UUID.
        $this->clientProphecy->request('get', '/account/ssh-keys/' . $mockBody->{'_embedded'}->items[0]->uuid)
            ->willReturn($mockBody->{'_embedded'}->items[0])
            ->shouldBeCalled();

        // Mock the delete request to return a valid OperationResponse.
        $operationResponse = $this->createMock(OperationResponse::class);
        $operationResponse->message = 'Successfully deleted SSH key';
        $this->clientProphecy->request('delete', '/account/ssh-keys/' . $mockBody->{'_embedded'}->items[0]->uuid)
            ->willReturn($operationResponse)
            ->shouldBeCalled();

        $inputs = [
            // Do you also want to delete the corresponding local key files?
            'n',
        ];

        $this->executeCommand([
            '--cloud-key-uuid' => $mockBody->{'_embedded'}->items[0]->uuid,
        ], $inputs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Successfully deleted SSH key', $output);
        $this->assertStringContainsString($mockBody->{'_embedded'}->items[0]->label, $output);
    }

    public function testDeleteDoesNotPromptWhenNoMatchingLocalKey(): void
    {
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();

        // Mock local SSH key that DOESN'T match the cloud key being deleted.
        $nonMatchingKey = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCnonmatching user@nonmatching';
        $this->mockLocalSshKeys([
            $this->createLocalKeyProphecy($nonMatchingKey, 'nonmatching.pub', '/path/to/nonmatching.pub'),
        ]);

        $this->mockRequest('deleteAccountSshKey', $mockBody->{'_embedded'}->items[0]->uuid, null, 'Removed key');

        $inputs = [
            // Choose key to delete.
            '0',
            // Should NOT prompt for local file deletion since keys don't match.
        ];
        $this->executeCommand([], $inputs);

        // Assert - should NOT contain the local file deletion prompt.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Successfully deleted SSH key', $output);
        $this->assertStringNotContainsString('Do you also want to delete the corresponding local key files', $output);
    }

    public function testDeletePromptsWhenMatchingLocalKeyExists(): void
    {
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();

        // Mock local SSH key that MATCHES the cloud key being deleted AND has a real path.
        $matchingKey = $mockBody->{'_embedded'}->items[0]->public_key;
        $this->mockLocalSshKeys([
            $this->createLocalKeyProphecy($matchingKey, 'matching.pub', '/path/to/matching.pub'),
        ]);

        $this->mockRequest('deleteAccountSshKey', $mockBody->{'_embedded'}->items[0]->uuid, null, 'Removed key');

        $inputs = [
            // Choose key to delete.
            '0',
            // Should prompt for local file deletion since keys match - answer 'n'.
            'n',
        ];
        $this->executeCommand([], $inputs);

        // Assert - should contain the local file deletion prompt.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Successfully deleted SSH key', $output);
        $this->assertStringContainsString('Do you also want to delete the corresponding local key files', $output);
    }

    public function testDeletePromptsWhenCloudKeyHasWhitespace(): void
    {
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');

        // Add whitespace to the cloud key's public_key field.
        $cloudKeyWithWhitespace = clone $mockBody->{'_embedded'}->items[0];
        $cloudKeyWithWhitespace->public_key = "  " . $mockBody->{'_embedded'}->items[0]->public_key . "\n";

        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn([$cloudKeyWithWhitespace, ...$mockBody->{'_embedded'}->items])
            ->shouldBeCalled();

        // Mock local SSH key that matches the cloud key (without whitespace)
        // Clean version.
        $localKeyContent = $mockBody->{'_embedded'}->items[0]->public_key;
        $this->mockLocalSshKeys([
            $this->createLocalKeyProphecy($localKeyContent, 'matching.pub', '/path/to/matching.pub'),
        ]);

        $this->mockRequest('deleteAccountSshKey', $cloudKeyWithWhitespace->uuid, null, 'Removed key');

        $inputs = [
            // Choose key to delete (the one with whitespace)
            '0',
            // Should prompt for local file deletion since trimmed keys match - answer 'n'.
            'n',
        ];
        $this->executeCommand([], $inputs);

        // Assert - should contain the local file deletion prompt (this would fail if trim() was removed from cloudKey)
        $output = $this->getDisplay();
        $this->assertStringContainsString('Successfully deleted SSH key', $output);
        $this->assertStringContainsString('Do you also want to delete the corresponding local key files', $output);
    }

    public function testDeletePromptsWhenLocalKeyHasWhitespace(): void
    {
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();

        // Mock local SSH key that has whitespace but matches the cloud key when trimmed.
        // Clean version.
        $cloudKeyContent = $mockBody->{'_embedded'}->items[0]->public_key;
        // Add whitespace.
        $localKeyWithWhitespace = "  " . $cloudKeyContent . "\n\t";
        $this->mockLocalSshKeys([
            $this->createLocalKeyProphecy($localKeyWithWhitespace, 'whitespace.pub', '/path/to/whitespace.pub'),
        ]);

        $this->mockRequest('deleteAccountSshKey', $mockBody->{'_embedded'}->items[0]->uuid, null, 'Removed key');

        $inputs = [
            // Choose key to delete.
            '0',
            // Should prompt for local file deletion since trimmed keys match - answer 'n'.
            'n',
        ];
        $this->executeCommand([], $inputs);

        // Assert - should contain the local file deletion prompt (this would fail if trim() was removed from localFileContents)
        $output = $this->getDisplay();
        $this->assertStringContainsString('Successfully deleted SSH key', $output);
        $this->assertStringContainsString('Do you also want to delete the corresponding local key files', $output);
    }

    // Helpers for mocking local keys using SplFileInfo and Finder.
    private function mockLocalSshKeys(array $keys): void
    {
        $finder = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $finder->method('files')->willReturnSelf();
        $finder->method('in')->willReturnSelf();
        $finder->method('name')->willReturnSelf();
        $finder->method('ignoreUnreadableDirs')->willReturnSelf();
        $finder->method('getIterator')->willReturn(new \ArrayIterator($keys));
        $localMachineHelper = $this->createMock(\Acquia\Cli\Helpers\LocalMachineHelper::class);
        $localMachineHelper->method('getFinder')->willReturn($finder);
        $this->command->localMachineHelper = $localMachineHelper;
    }

    private function createLocalKeyProphecy(string $publicKey, string $filename, string $realPath): \Symfony\Component\Finder\SplFileInfo|\PHPUnit\Framework\MockObject\MockObject
    {
        $file = $this->createMock(\Symfony\Component\Finder\SplFileInfo::class);
        $file->method('getContents')->willReturn($publicKey);
        $file->method('getFilename')->willReturn($filename);
        $file->method('getRealPath')->willReturn($realPath);
        return $file;
    }
}
