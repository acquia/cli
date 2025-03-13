<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Endpoints\SshKeys;
use AcquiaCloudApi\Response\OperationResponse;
use AcquiaCloudApi\Response\SshKeyResponse;
use PHPUnit\Framework\MockObject\Exception;

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
}
