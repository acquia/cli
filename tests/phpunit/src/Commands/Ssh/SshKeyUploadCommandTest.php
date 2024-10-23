<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class SshKeyUploadCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(SshKeyUploadCommand::class);
    }

    /**
     * @return array<mixed>
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function providerTestUpload(): array
    {
        $sshKeysRequestBody = self::getMockRequestBodyFromSpec('/account/ssh-keys');
        return [
            [
                // Args.
                [],
                // Inputs.
                [
                    // Choose key.
                    '0',
                    // Enter a Cloud Platform label for this SSH key:
                    $sshKeysRequestBody['label'],
                    // Would you like to wait until Cloud Platform is ready? (yes/no)
                    'y',
                    // Would you like Acquia CLI to search for a Cloud application that matches your local git config? (yes/no)
                    'n',
                ],
                // Perms.
                true,
            ],
            [
                // Args.
                [
                    '--filepath' => 'id_rsa.pub',
                    '--label' => $sshKeysRequestBody['label'],
                ],
                // Inputs.
                [
                    // Would you like to wait until Cloud Platform is ready? (yes/no)
                    'y',
                    // Would you like Acquia CLI to search for a Cloud application that matches your local git config? (yes/no)
                    'n',
                ],
                // Perms.
                false,
            ],
        ];
    }

    /**
     * @dataProvider providerTestUpload
     */
    public function testUpload(array $args, array $inputs, bool $perms): void
    {
        $sshKeysRequestBody = self::getMockRequestBodyFromSpec('/account/ssh-keys');
        $body = [
            'json' => [
                'label' => $sshKeysRequestBody['label'],
                'public_key' => $sshKeysRequestBody['public_key'],
            ],
        ];
        $this->mockRequest('postAccountSshKeys', null, $body);
        $this->mockListSshKeyRequestWithUploadedKey($sshKeysRequestBody);
        $applicationsResponse = $this->mockApplicationsRequest();
        $applicationResponse = $this->mockApplicationRequest();
        $this->mockPermissionsRequest($applicationResponse, $perms);

        $localMachineHelper = $this->mockLocalMachineHelper();
        /** @var Filesystem|\Prophecy\Prophecy\ObjectProphecy $fileSystem */
        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $fileName = $this->mockGetLocalSshKey($localMachineHelper, $fileSystem, $sshKeysRequestBody['public_key']);

        $localMachineHelper->getFilesystem()->willReturn($fileSystem);
        $fileSystem->exists(Argument::type('string'))->willReturn(true);
        $localMachineHelper->getLocalFilepath(Argument::containingString('id_rsa'))
            ->willReturn('id_rsa.pub');
        $localMachineHelper->readFile(Argument::type('string'))
            ->willReturn($sshKeysRequestBody['public_key']);

        if ($perms) {
            $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
            $sshHelper = $this->mockPollCloudViaSsh($environmentsResponse->_embedded->items);
            $this->command->sshHelper = $sshHelper->reveal();
        }

        // Choose a local SSH key to upload to the Cloud Platform.
        $this->executeCommand($args, $inputs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString("Uploaded $fileName to the Cloud Platform with label " . $sshKeysRequestBody['label'], $output);
        $this->assertStringContainsString('Would you like to wait until your key is installed on all of your application\'s servers?', $output);
        $this->assertStringContainsString('Your SSH key is ready for use!', $output);
    }

    // Ensure permission checks aren't against a Node environment.
    public function testUploadNode(): void
    {
        $sshKeysRequestBody = self::getMockRequestBodyFromSpec('/account/ssh-keys');
        $body = [
            'json' => [
                'label' => $sshKeysRequestBody['label'],
                'public_key' => $sshKeysRequestBody['public_key'],
            ],
        ];
        $this->mockRequest('postAccountSshKeys', null, $body);
        $this->mockListSshKeyRequestWithUploadedKey($sshKeysRequestBody);
        $applicationsResponse = $this->mockApplicationsRequest();
        $applicationResponse = $this->mockApplicationRequest();
        $this->mockPermissionsRequest($applicationResponse, true);

        $localMachineHelper = $this->mockLocalMachineHelper();
        /** @var Filesystem|\Prophecy\Prophecy\ObjectProphecy $fileSystem */
        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $fileName = $this->mockGetLocalSshKey($localMachineHelper, $fileSystem, $sshKeysRequestBody['public_key']);

        $localMachineHelper->getFilesystem()->willReturn($fileSystem);
        $fileSystem->exists(Argument::type('string'))->willReturn(true);
        $localMachineHelper->getLocalFilepath(Argument::containingString('id_rsa'))
            ->willReturn('id_rsa.pub');
        $localMachineHelper->readFile(Argument::type('string'))
            ->willReturn($sshKeysRequestBody['public_key']);

        $tamper = function ($responses): void {
            foreach ($responses as $response) {
                $response->type = 'node';
            }
        };
        $environmentsResponse = $this->mockRequest('getApplicationEnvironments', $applicationsResponse->_embedded->items[0]->uuid, null, null, $tamper);
        $sshHelper = $this->mockPollCloudViaSsh($environmentsResponse, false);
        $this->command->sshHelper = $sshHelper->reveal();

        // Choose a local SSH key to upload to the Cloud Platform.
        $inputs = [
            // Choose key.
            '0',
            // Enter a Cloud Platform label for this SSH key:
            $sshKeysRequestBody['label'],
            // Would you like to wait until Cloud Platform is ready? (yes/no)
            'y',
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config? (yes/no)
            'n',
        ];
        $this->executeCommand([], $inputs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString("Uploaded $fileName to the Cloud Platform with label " . $sshKeysRequestBody['label'], $output);
        $this->assertStringContainsString('Would you like to wait until your key is installed on all of your application\'s servers?', $output);
        $this->assertStringContainsString('Your SSH key is ready for use!', $output);
    }

    public function testInvalidFilepath(): void
    {
        $inputs = [
            // Choose key.
            '0',
            // Label.
            'Test',
        ];
        $filepath = Path::join(sys_get_temp_dir(), 'notarealfile');
        $args = ['--filepath' => $filepath];
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage("The filepath $filepath is not valid");
        $this->executeCommand($args, $inputs);
    }
}
