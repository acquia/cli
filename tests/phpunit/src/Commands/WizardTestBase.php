<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

abstract class WizardTestBase extends CommandTestBase
{
    public static string $applicationUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

    protected string $sshKeyFileName;

    /**
     * This method is called before each test.
     */
    public function setUp(): void
    {
        self::setEnvVars(self::getEnvVars());
        parent::setUp();
        $this->getCommandTester();
        $this->application->addCommands([
            $this->injectCommand(SshKeyCreateCommand::class),
            $this->injectCommand(SshKeyDeleteCommand::class),
            $this->injectCommand(SshKeyUploadCommand::class),
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::unsetEnvVars(self::getEnvVars());
    }

    /**
     * @return array<string>
     */
    public static function getEnvVars(): array
    {
        return [
            'ACQUIA_APPLICATION_UUID' => self::$applicationUuid,
        ];
    }

    protected function runTestCreate(): void
    {
        $environmentsResponse = self::getMockEnvironmentsResponse();
        $this->clientProphecy->request('get', "/applications/" . $this::$applicationUuid . "/environments")
            ->willReturn($environmentsResponse->_embedded->items)
            ->shouldBeCalled();
        $request = self::getMockRequestBodyFromSpec('/account/ssh-keys');

        $body = [
            'json' => [
                'label' => 'IDE_ExampleIDE_215824ff272a4a8c9027df32ed1d68a9',
                'public_key' => $request['public_key'],
            ],
        ];
        $this->mockRequest('postAccountSshKeys', null, $body);

        $localMachineHelper = $this->mockLocalMachineHelper();

        // Poll Cloud.
        $sshHelper = $this->mockPollCloudViaSsh($environmentsResponse->_embedded->items);
        $this->command->sshHelper = $sshHelper->reveal();

        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $this->mockGenerateSshKey($localMachineHelper, $request['public_key']);
        $localMachineHelper->getLocalFilepath($this->passphraseFilepath)
            ->willReturn($this->passphraseFilepath);
        $fileSystem->remove(Argument::size(2))->shouldBeCalled();
        $this->mockAddSshKeyToAgent($localMachineHelper, $fileSystem);
        $this->mockSshAgentList($localMachineHelper);
        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();

        /** @var SshKeyCreateCommand $sshKeyCreateCommand */
        $sshKeyCreateCommand = $this->application->find(SshKeyCreateCommand::getDefaultName());
        $sshKeyCreateCommand->localMachineHelper = $this->command->localMachineHelper;
        /** @var SshKeyUploadCommand $sshKeyUploadCommand */
        $sshKeyUploadCommand = $this->application->find(SshKeyUploadCommand::getDefaultName());
        $sshKeyUploadCommand->localMachineHelper = $this->command->localMachineHelper;
        /** @var SshKeyDeleteCommand $sshKeyDeleteCommand */
        $sshKeyDeleteCommand = $this->application->find(SshKeyDeleteCommand::getDefaultName());
        $sshKeyDeleteCommand->localMachineHelper = $this->command->localMachineHelper;

        // Remove SSH key if it exists.
        $this->fs->remove(Path::join(sys_get_temp_dir(), $this->sshKeyFileName));

        // Set properties and execute.
        $this->executeCommand([], [
            // Would you like to link the project at ... ?
            'y',
        ]);

        // Assertions.
        $display = $this->getDisplay();
        // Assert success message is shown.
        $this->assertStringContainsString('[NOTE] It may take an hour or more before the SSH key is installed', $display);


        // Ensure command returned success code.
        $this->assertSame($this->command::SUCCESS, $this->getStatusCode());
    }

    protected function runTestSshKeyCodebaseUuidExists(): void
    {
        self::setEnvVars([
            'AH_CODEBASE_UUID' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        ]);
        $environmentsResponse = self::getMockEnvironmentsResponse();

        $request = self::getMockRequestBodyFromSpec('/account/ssh-keys');

        $body = [
            'json' => [
                'label' => 'IDE_ExampleIDE_215824ff272a4a8c9027df32ed1d68a9',
                'public_key' => $request['public_key'],
            ],
        ];
        $this->mockRequest('postAccountSshKeys', null, $body);

        $localMachineHelper = $this->mockLocalMachineHelper();

        // Poll Cloud.
        $sshHelper = $this->mockPollCloudViaSsh($environmentsResponse->_embedded->items);
        $this->command->sshHelper = $sshHelper->reveal();

        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $this->mockGenerateSshKey($localMachineHelper, $request['public_key']);
        $localMachineHelper->getLocalFilepath($this->passphraseFilepath)
            ->willReturn($this->passphraseFilepath);
        $fileSystem->remove(Argument::size(2))->shouldBeCalled();
        $this->mockAddSshKeyToAgent($localMachineHelper, $fileSystem);
        $this->mockSshAgentList($localMachineHelper);
        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();

        /** @var SshKeyCreateCommand $sshKeyCreateCommand */
        $sshKeyCreateCommand = $this->application->find(SshKeyCreateCommand::getDefaultName());
        $sshKeyCreateCommand->localMachineHelper = $this->command->localMachineHelper;
        /** @var SshKeyUploadCommand $sshKeyUploadCommand */
        $sshKeyUploadCommand = $this->application->find(SshKeyUploadCommand::getDefaultName());
        $sshKeyUploadCommand->localMachineHelper = $this->command->localMachineHelper;
        /** @var SshKeyDeleteCommand $sshKeyDeleteCommand */
        $sshKeyDeleteCommand = $this->application->find(SshKeyDeleteCommand::getDefaultName());
        $sshKeyDeleteCommand->localMachineHelper = $this->command->localMachineHelper;

        // Remove SSH key if it exists.
        $this->fs->remove(Path::join(sys_get_temp_dir(), $this->sshKeyFileName));

        // Set properties and execute.
        $this->executeCommand([], [
            // Would you like to link the project at ... ?
            'y',
        ]);

        // Assertions.
        $display = $this->getDisplay();
        // Assert success message is shown.
        $this->assertStringContainsString('SSH key has been successfully uploaded to the Cloud Platform', $display);
        $this->assertStringContainsString('[NOTE] It may take an hour or more before the SSH key is installed', $display);


        // Ensure command returned success code.
        $this->assertSame($this->command::SUCCESS, $this->getStatusCode());
        self::unsetEnvVars([
            'AH_CODEBASE_UUID' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        ]);
    }
    protected function runTestPromptWaitForSshReturnsTrue(): void
    {

        $environmentsResponse = self::getMockEnvironmentsResponse();
        $this->clientProphecy->request('get', "/applications/" . $this::$applicationUuid . "/environments")
            ->willReturn($environmentsResponse->_embedded->items)
            ->shouldBeCalled();

        $request = self::getMockRequestBodyFromSpec('/account/ssh-keys');

        $body = [
            'json' => [
                'label' => 'IDE_ExampleIDE_215824ff272a4a8c9027df32ed1d68a9',
                'public_key' => $request['public_key'],
            ],
        ];
        $this->mockRequest('postAccountSshKeys', null, $body);

        $localMachineHelper = $this->mockLocalMachineHelper();

        // Poll Cloud.
        $sshHelper = $this->mockPollCloudViaSsh($environmentsResponse->_embedded->items);
        $this->command->sshHelper = $sshHelper->reveal();

        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $this->mockGenerateSshKey($localMachineHelper, $request['public_key']);
        $localMachineHelper->getLocalFilepath($this->passphraseFilepath)
            ->willReturn($this->passphraseFilepath);
        $fileSystem->remove(Argument::size(2))->shouldBeCalled();
        $this->mockAddSshKeyToAgent($localMachineHelper, $fileSystem);
        $this->mockSshAgentList($localMachineHelper);
        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();

        /** @var SshKeyCreateCommand $sshKeyCreateCommand */
        $sshKeyCreateCommand = $this->application->find(SshKeyCreateCommand::getDefaultName());
        $sshKeyCreateCommand->localMachineHelper = $this->command->localMachineHelper;
        /** @var SshKeyUploadCommand $sshKeyUploadCommand */
        $sshKeyUploadCommand = $this->application->find(SshKeyUploadCommand::getDefaultName());
        $sshKeyUploadCommand->localMachineHelper = $this->command->localMachineHelper;
        /** @var SshKeyDeleteCommand $sshKeyDeleteCommand */
        $sshKeyDeleteCommand = $this->application->find(SshKeyDeleteCommand::getDefaultName());
        $sshKeyDeleteCommand->localMachineHelper = $this->command->localMachineHelper;

        // Remove SSH key if it exists.
        $this->fs->remove(Path::join(sys_get_temp_dir(), $this->sshKeyFileName));

        // Set properties and execute.
        $this->executeCommand([], [
            // Would you like to wait until your key is installed on all of your application's servers?
            'y',
        ]);

        // Assertions.
        $display = $this->getDisplay();
        // Assert success message is shown.
        $this->assertStringContainsString('Your SSH key is ready for use!', $display);
        // Ensure command returned success code.
        $this->assertSame($this->command::SUCCESS, $this->getStatusCode());
    }


    protected function runTestPromptWaitForSshReturnsFalse(): void
    {

        $environmentsResponse = self::getMockEnvironmentsResponse();
        $this->clientProphecy->request('get', "/applications/" . $this::$applicationUuid . "/environments")
            ->willReturn($environmentsResponse->_embedded->items)
            ->shouldBeCalled();

        $request = self::getMockRequestBodyFromSpec('/account/ssh-keys');

        $body = [
            'json' => [
                'label' => 'IDE_ExampleIDE_215824ff272a4a8c9027df32ed1d68a9',
                'public_key' => $request['public_key'],
            ],
        ];
        $this->mockRequest('postAccountSshKeys', null, $body);

        $localMachineHelper = $this->mockLocalMachineHelper();

        // Poll Cloud.
        $sshHelper = $this->mockPollCloudViaSsh($environmentsResponse->_embedded->items);
        $this->command->sshHelper = $sshHelper->reveal();

        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $this->mockGenerateSshKey($localMachineHelper, $request['public_key']);
        $localMachineHelper->getLocalFilepath($this->passphraseFilepath)
            ->willReturn($this->passphraseFilepath);
        $fileSystem->remove(Argument::size(2))->shouldBeCalled();
        $this->mockAddSshKeyToAgent($localMachineHelper, $fileSystem);
        $this->mockSshAgentList($localMachineHelper);
        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();

        /** @var SshKeyCreateCommand $sshKeyCreateCommand */
        $sshKeyCreateCommand = $this->application->find(SshKeyCreateCommand::getDefaultName());
        $sshKeyCreateCommand->localMachineHelper = $this->command->localMachineHelper;
        /** @var SshKeyUploadCommand $sshKeyUploadCommand */
        $sshKeyUploadCommand = $this->application->find(SshKeyUploadCommand::getDefaultName());
        $sshKeyUploadCommand->localMachineHelper = $this->command->localMachineHelper;
        /** @var SshKeyDeleteCommand $sshKeyDeleteCommand */
        $sshKeyDeleteCommand = $this->application->find(SshKeyDeleteCommand::getDefaultName());
        $sshKeyDeleteCommand->localMachineHelper = $this->command->localMachineHelper;

        // Remove SSH key if it exists.
        $this->fs->remove(Path::join(sys_get_temp_dir(), $this->sshKeyFileName));

        // Set properties and execute.
        $this->executeCommand([], [
            // Would you like to wait until your key is installed on all of your application's servers?
            'n',
        ]);

                // Assertions.
        $display = $this->getDisplay();
        // Assert success message is shown.
        $this->assertStringContainsString('Your SSH key has been successfully uploaded to the Cloud Platform.', $display);


        // Ensure command returned success code.
        $this->assertSame($this->command::SUCCESS, $this->getStatusCode());
    }

    protected function runTestSshKeyAlreadyUploaded(): void
    {
        $mockRequestArgs = self::getMockRequestBodyFromSpec('/account/ssh-keys');
        $sshKeysResponse = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        // Make the uploaded key match the created one.
        $sshKeysResponse->_embedded->items[0]->public_key = $mockRequestArgs['public_key'];
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($sshKeysResponse->{'_embedded'}->items)
            ->shouldBeCalled();

        $this->clientProphecy->request('get', '/account/ssh-keys/' . $sshKeysResponse->_embedded->items[0]->uuid)
            ->willReturn($sshKeysResponse->{'_embedded'}->items[0])
            ->shouldBeCalled();

        $deleteResponse = $this->prophet->prophesize(ResponseInterface::class);
        $deleteResponse->getStatusCode()->willReturn(202);
        $this->clientProphecy->makeRequest('delete', '/account/ssh-keys/' . $sshKeysResponse->_embedded->items[0]->uuid)
            ->willReturn($deleteResponse->reveal())
            ->shouldBeCalled();

        $environmentsResponse = self::getMockEnvironmentsResponse();
        $this->clientProphecy->request('get', "/applications/" . $this::$applicationUuid . "/environments")
            ->willReturn($environmentsResponse->_embedded->items)
            ->shouldBeCalled();

        $localMachineHelper = $this->mockLocalMachineHelper();

        $body = [
            'json' => [
                'label' => 'IDE_ExampleIDE_215824ff272a4a8c9027df32ed1d68a9',
                'public_key' => $mockRequestArgs['public_key'],
            ],
        ];
        $this->mockRequest('postAccountSshKeys', null, $body);

        // Poll Cloud.
        $sshHelper = $this->mockPollCloudViaSsh($environmentsResponse->_embedded->items);
        $this->command->sshHelper = $sshHelper->reveal();

        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $this->mockGenerateSshKey($localMachineHelper, $mockRequestArgs['public_key']);
        $fileSystem->remove(Argument::size(2))->shouldBeCalled();
        $this->mockAddSshKeyToAgent($localMachineHelper, $fileSystem);
        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();
        $this->mockSshAgentList($localMachineHelper);

        $this->application->find(SshKeyCreateCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;
        $this->application->find(SshKeyUploadCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;
        $this->application->find(SshKeyDeleteCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;

        $this->createLocalSshKey($mockRequestArgs['public_key']);
        $this->executeCommand();
    }

    /**
     * @return string[]
     *   An array of strings to inspect the output for.
     */
    protected function getOutputStrings(): array
    {
        return [
            "Setting GitLab CI/CD variables for",
        ];
    }
}
