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
    public static function providerTestCloudAppUuidArg(): array
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
    public static function providerTestInvalidCloudAppUuidArg(): array
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
    public static function providerTestInvalidCloudEnvironmentAlias(): array
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

    /**
     * Test determineCloudCodebase throws exception when doDetermineCloudCodebase returns null.
     */
    public function testDetermineCloudCodebaseThrowsExceptionWhenNoCodebaseFound(): void
    {
        // Mock the input interface to return false for isInteractive() and hasArgument()
        $inputMock = $this->prophet->prophesize(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->isInteractive()->willReturn(false);
        $inputMock->hasArgument('codebaseId')->willReturn(false);
        $inputMock->getArgument('codebaseId')->willReturn(null);

        // Set the mocked input on the command.
        $reflection = new \ReflectionClass($this->command);
        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($this->command, $inputMock->reveal());

        // Test the determineCloudCodebase method which should throw an exception.
        $method = $reflection->getMethod('determineCloudCodebase');
        $method->setAccessible(true);

        $this->expectException(\Acquia\Cli\Exception\AcquiaCliException::class);
        $this->expectExceptionMessage('Could not determine Cloud Codebase');

        // Call the method - it should throw an exception when doDetermineCloudCodebase returns null.
        $method->invoke($this->command);
    }

    /**
     * Test determineCloudCodebase with no interactive input return null.
     */
    public function testDetermineCloudCodebaseReturnNullWithNoInteractiveInput(): void
    {
        // Mock the input interface to return false for isInteractive() and hasArgument()
        $inputMock = $this->prophet->prophesize(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->isInteractive()->willReturn(false);
        $inputMock->hasArgument('codebaseId')->willReturn(false);
        $inputMock->getArgument('codebaseId')->willReturn(null);

        // Set the mocked input on the command.
        $reflection = new \ReflectionClass($this->command);
        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($this->command, $inputMock->reveal());

        // Test the doDetermineCloudCodebase method since determineCloudCodebase will throw an exception.
        $method = $reflection->getMethod('doDetermineCloudCodebase');
        $method->setAccessible(true);

        // Call the method - it should return null when not interactive.
        $result = $method->invoke($this->command);

        // Assert that the result is null.
        $this->assertNull($result);
    }

    /**
     * Test getCloudCodebase method.
     */
    public function testGetCloudCodebase(): void
    {
        $codebaseUuid = 'test-codebase-uuid';
        $expectedCodebase = (object) [
            'applications_total' => 0,
            'created_at' => '2024-12-20T06:39:50.000Z',
            'description' => '',
            'flags' => (object) [
                'active' => 1,
            ],
            'hash' => 'ryh4smn',
            'id' => $codebaseUuid,
            'label' => 'Test Codebase',
            'region' => 'us-east-1',
            'repository_id' => 'a5ef0a9d-44ce-4f06-8d4f-15f24f941a74',
            'updated_at' => '2024-12-20T06:39:50.000Z',
            'vcs_url' => 'ssh://us-east-1.dev.vcs.acquia.io/test-codebase-uuid',
            '_embedded' => (object) [
                'subscription' => (object) [
                    'id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
                    '_links' => (object) [
                        'self' => (object) [
                            'href' => 'https://cloud.acquia.com/api/subscriptions/f47ac10b-58cc-4372-a567-0e02b2c3d479',
                        ],
                    ],
                ],
            ],
            '_links' => (object) [
                'applications' => (object) [
                    'href' => 'https://cloud.acquia.com/api/codebases/' . $codebaseUuid . '/applications',
                ],
                'self' => (object) [
                    'href' => 'https://cloud.acquia.com/api/codebases',
                ],
                'subscription' => (object) [
                    'href' => 'https://cloud.acquia.com/api/subscriptions/f47ac10b-58cc-4372-a567-0e02b2c3d479',
                ],
            ],
        ];

        $this->clientProphecy->request('get', '/codebases/' . $codebaseUuid)
            ->willReturn($expectedCodebase)
            ->shouldBeCalled();

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getCloudCodebase');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $codebaseUuid);
        $this->assertEquals($expectedCodebase->id, $result->id);
        $this->assertEquals($expectedCodebase->label, $result->label);
    }

    /**
     * Test promptChooseCodebase with no codebases throws exception.
     */
    public function testPromptChooseCodebaseThrowsExceptionForNoCodebases(): void
    {
        // Mock empty codebases response as an empty array (no codebases available)
        $this->clientProphecy->request('get', '/codebases')
            ->willReturn([])
            ->shouldBeCalled();

        // Use the already set up client prophecy.
        $client = $this->clientProphecy->reveal();

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('promptChooseCodebase');
        $method->setAccessible(true);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('You have no Cloud codebases.');

        $method->invoke($this->command, $client);
    }

    /**
     * Test doDetermineCloudCodebase method with valid codebaseId argument.
     */
    public function testDoDetermineCloudCodebaseWithValidArgument(): void
    {
        $codebaseId = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

        // Mock input to return the codebaseId argument.
        $inputMock = $this->prophet->prophesize(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->hasArgument('codebaseId')->willReturn(true);
        $inputMock->getArgument('codebaseId')->willReturn($codebaseId);

        // Set the mocked input on the command.
        $reflection = new \ReflectionClass($this->command);
        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($this->command, $inputMock->reveal());

        // Test the doDetermineCloudCodebase method.
        $method = $reflection->getMethod('doDetermineCloudCodebase');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        // Should return the validated UUID.
        $this->assertEquals($codebaseId, $result);
    }

    /**
     * Test doDetermineCloudCodebase method with hasArgument returning false.
     */
    public function testDoDetermineCloudCodebaseWithoutArgument(): void
    {
        // Mock input to return false for hasArgument.
        $inputMock = $this->prophet->prophesize(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->hasArgument('codebaseId')->willReturn(false);
        $inputMock->isInteractive()->willReturn(false);
        $inputMock->getArgument('codebaseId')->willReturn(null);

        // Set the mocked input on the command.
        $reflection = new \ReflectionClass($this->command);
        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($this->command, $inputMock->reveal());

        // Test the doDetermineCloudCodebase method.
        $method = $reflection->getMethod('doDetermineCloudCodebase');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        // Should return null when not interactive and no argument.
        $this->assertNull($result);
    }

    /**
     * Test doDetermineCloudCodebase method with hasArgument true but getArgument returns null.
     */
    public function testDoDetermineCloudCodebaseWithNullArgument(): void
    {
        // Mock input with hasArgument true but getArgument returns null/empty.
        $inputMock = $this->prophet->prophesize(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->hasArgument('codebaseId')->willReturn(true);
        $inputMock->getArgument('codebaseId')->willReturn(null);
        $inputMock->isInteractive()->willReturn(false);

        // Set the mocked input on the command.
        $reflection = new \ReflectionClass($this->command);
        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($this->command, $inputMock->reveal());

        // Test the doDetermineCloudCodebase method.
        $method = $reflection->getMethod('doDetermineCloudCodebase');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        // Should return null when argument is null even if hasArgument is true.
        $this->assertNull($result);
    }

    /**
     * Test acceptCodebaseId method adds the correct argument and usage.
     */
    public function testAcceptCodebaseId(): void
    {
        // Get a fresh command instance.
        $command = $this->createCommand();

        // Use reflection to call the protected method.
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('acceptCodebaseId');
        $method->setAccessible(true);

        // Call acceptCodebaseId method.
        $result = $method->invoke($command);

        // Verify it returns the command instance for method chaining.
        $this->assertSame($command, $result);

        // Verify the argument was added with correct configuration.
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('codebaseId'));

        $argument = $definition->getArgument('codebaseId');
        $this->assertSame('codebaseId', $argument->getName());
        $this->assertSame('The Cloud Platform codebase ID', $argument->getDescription());
        $this->assertFalse($argument->isRequired(), 'codebaseId argument should be optional');

        // Verify the usage example was added.
        $usages = $command->getUsages();
        $this->assertGreaterThan(0, count($usages), 'Command should have usage examples');

        // Check that the usage contains the expected UUID format.
        $usageFound = false;
        foreach ($usages as $usage) {
            if (strpos($usage, 'abcd1234-1111-2222-3333-0e02b2c3d470') !== false) {
                $usageFound = true;
                break;
            }
        }
        $this->assertTrue($usageFound, 'Usage should contain the expected UUID example. Found usages: ' . implode(', ', $usages));
    }

    /**
     * Test acceptCodebaseUuid method adds the correct option and usage.
     */
    public function testAcceptCodebaseUuid(): void
    {
        // Get a fresh command instance.
        $command = $this->createCommand();

        // Use reflection to call the protected method.
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('acceptCodebaseUuid');
        $method->setAccessible(true);

        // Call acceptCodebaseUuid method.
        $result = $method->invoke($command);

        // Verify it returns the command instance for method chaining.
        $this->assertSame($command, $result);

        // Verify the argument was added with correct configuration.
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('codebaseUuid'));

        $option = $definition->getOption('codebaseUuid');
        $this->assertSame('codebaseUuid', $option->getName());
        $this->assertSame('The Cloud Platform codebase UUID', $option->getDescription());
        $this->assertFalse($option->isValueRequired(), 'codebaseUuid option should be optional');

        // Verify the usage example was added.
        $usages = $command->getUsages();
        $this->assertGreaterThan(0, count($usages), 'Command should have usage examples');

        // Check that the usage contains the expected UUID format.
        $usageFound = false;
        foreach ($usages as $usage) {
            if (strpos($usage, 'abcd1234-1111-2222-3333-0e02b2c3d470') !== false) {
                $usageFound = true;
                break;
            }
        }
        $this->assertTrue($usageFound, 'Usage should contain the expected UUID example. Found usages: ' . implode(', ', $usages));
    }

    /**
     * Test siteInstanceId method adds the correct option and usage.
     */
    public function testAcceptSiteInstanceId(): void
    {
        // Get a fresh command instance.
        $command = $this->createCommand();

        // Use reflection to call the protected method.
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('acceptSiteInstanceId');
        $method->setAccessible(true);

        // Call acceptSiteInstanceId method.
        $result = $method->invoke($command);

        // Verify it returns the command instance for method chaining.
        $this->assertSame($command, $result);

        // Verify the option was added with correct configuration.
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('siteInstanceId'));

        $option = $definition->getOption('siteInstanceId');
        $this->assertSame('siteInstanceId', $option->getName());
        $this->assertSame('The Site Instance ID', $option->getDescription());
        $this->assertFalse($option->isValueRequired(), 'siteInstanceId option should be optional');

        // Verify the usage example was added.
        $usages = $command->getUsages();
        $this->assertGreaterThan(0, count($usages), 'Command should have usage examples');

        // Check that the usage contains the expected UUID format.
        $usageFound = false;
        foreach ($usages as $usage) {
            if (strpos($usage, 'abcd1234-1111-2222-3333-0e02b2c3d470') !== false) {
                $usageFound = true;
                break;
            }
        }
        $this->assertTrue($usageFound, 'Usage should contain the expected UUID example. Found usages: ' . implode(', ', $usages));
    }
}
