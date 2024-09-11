<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\App\LinkCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeListCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property LinkCommand $command
 */
class CommandBaseTest extends CommandTestBase
{
    /**
     * @return \Acquia\Cli\Command\App\LinkCommand
     */
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(LinkCommand::class);
    }

    public function testUnauthenticatedFailure(): void
    {
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockConfigFiles();

        $inputs = [
            // Would you like to share anonymous performance usage and data?
            'n',
        ];
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('This machine is not yet authenticated with the Cloud Platform.');
        $this->executeCommand([], $inputs);
    }

    public function testCloudAppFromLocalConfig(): void
    {
        $this->mockRequest('getApplicationIdes', 'a47ac10b-58cc-4372-a567-0e02b2c3d470');
        $this->createMockAcliConfigFile('a47ac10b-58cc-4372-a567-0e02b2c3d470');
        $this->createDataStores();
        $this->command = $this->injectCommand(IdeListCommand::class);
        $this->executeCommand();
    }

    /**
     * @return string[][]
     */
    public function providerTestCloudAppUuidArg(): array
    {
        return [
            ['a47ac10b-58cc-4372-a567-0e02b2c3d470'],
            ['165c887b-7633-4f64-799d-a5d4669c768e'],
        ];
    }

    /**
     * @dataProvider providerTestCloudAppUuidArg
     * @group brokenProphecy
     */
    public function testCloudAppUuidArg(string $uuid): void
    {
        $this->mockApplicationRequest();
        $this->assertEquals($uuid, CommandBase::validateUuid($uuid));
    }

    /**
     * @return array<mixed>
     */
    public function providerTestInvalidCloudAppUuidArg(): array
    {
        return [
            [
                'a47ac10b-58cc-4372-a567-0e02b2c3d4',
                'This value should have exactly 36 characters.',
            ],
            [
                'a47ac10b-58cc-4372-a567-0e02b2c3d47z',
                'This is not a valid UUID.',
            ],
        ];
    }

    /**
     * @dataProvider providerTestInvalidCloudAppUuidArg
     */
    public function testInvalidCloudAppUuidArg(string $uuid, string $message): void
    {
        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage($message);
        CommandBase::validateUuid($uuid);
    }

    /**
     * @return array<mixed>
     */
    public function providerTestInvalidCloudEnvironmentAlias(): array
    {
        return [
            [
                'bl.a',
                'This value is too short. It should have 5 characters or more.',
            ],
            [
                'blarg',
                'You must enter either an environment ID or alias. Environment aliases must match the pattern [app-name].[env]',
            ],
            [
                '12345',
                'You must enter either an environment ID or alias. Environment aliases must match the pattern [app-name].[env]',
            ],
        ];
    }

    /**
     * @dataProvider providerTestInvalidCloudEnvironmentAlias
     */
    public function testInvalidCloudEnvironmentAlias(string $alias, string $message): void
    {
        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage($message);
        CommandBase::validateEnvironmentAlias($alias);
    }
}
