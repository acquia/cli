<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Command\Auth\AuthAcsfLoginCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property \Acquia\Cli\Command\Auth\AuthLoginCommand $command
 */
class AcsfAuthLoginCommandTest extends AcsfCommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
        return $this->injectCommand(AuthAcsfLoginCommand::class);
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestAuthLoginCommand(): array
    {
        return [
            // Data set 0.
            [
                // $machineIsAuthenticated
                false,
                // $inputs
                [
                    // Would you like to share anonymous performance usage and data? (yes/no) [yes].
                    'yes',
                    // Enter the full URL of the factory.
                    self::$acsfCurrentFactoryUrl,
                    // Enter a value for username.
                    self::$acsfUsername,
                    // Enter a value for key.
                    self::$acsfKey,
                ],
                // No arguments, all interactive.
                [],
                // Output to assert.
                'Saved credentials',
            ],
            // Data set 1.
            [
                // $machineIsAuthenticated
                false,
                // $inputs
                [],
                // Arguments.
                [
                    // Enter the full URL of the factory.
                    '--factory-url' => self::$acsfCurrentFactoryUrl,
                    // Enter a value for key.
                    '--key' => self::$acsfKey,
                    // Enter a value for username.
                    '--username' => self::$acsfUsername,
                ],
                // Output to assert.
                'Saved credentials',
                // $config.
                AcsfCommandTestBase::getAcsfCredentialsFileContents(),
            ],
            // Data set 2.
            [
                // $machineIsAuthenticated
                true,
                // $inputs
                [
                    // Choose a factory to log in to.
                    self::$acsfCurrentFactoryUrl,
                    // Choose which user to log in as.
                    self::$acsfUsername,
                ],
                // Arguments.
                [],
                // Output to assert.
                'Acquia CLI is now logged in to ' . self::$acsfCurrentFactoryUrl . ' as ' . self::$acsfUsername,
                // $config.
                AcsfCommandTestBase::getAcsfCredentialsFileContents(),
            ],
            // Data set 3.
            [
                true,
                [
                    'Enter a new factory URL',
                    self::$acsfCurrentFactoryUrl,
                    'Enter a new user',
                    self::$acsfUsername,
                    self::$acsfKey,
                ],
                [],
                // Output to assert.
                'Enter your Site Factory URL (including https://) (option -f, --factory-url):',
                // $config.
                AcsfCommandTestBase::getAcsfCredentialsFileContents(),
            ],
            // Data set 4.
            [
                false,
                [
                    'Enter a new factory URL',
                    self::$acsfCurrentFactoryUrl,
                    self::$acsfUsername,
                    self::$acsfKey,
                ],
                [],
                // Output to assert.
                'Enter your Site Factory URL (including https://) (option -f, --factory-url):',
                // $config.
                AcsfCommandTestBase::getAcsfCredentialsFileContents(),
            ],
        ];
    }

    /**
     * @dataProvider providerTestAuthLoginCommand
     * @requires OS linux|darwin
     */
    public function testAcsfAuthLoginCommand(bool $machineIsAuthenticated, array $inputs, array $args, string $outputToAssert, array $config = []): void
    {
        if (!$machineIsAuthenticated) {
            $this->clientServiceProphecy->isMachineAuthenticated()
                ->willReturn(false);
            $this->removeMockCloudConfigFile();
        } else {
            $this->removeMockCloudConfigFile();
            $this->createMockCloudConfigFile($config);
        }
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand($args, $inputs);
        $output = $this->getDisplay();
        $this->assertStringContainsString($outputToAssert, $output);
        if (!$machineIsAuthenticated && !array_key_exists('--key', $args)) {
            $this->assertStringContainsString('Enter your Site Factory key (option -k, --key) (input will be hidden):', $output);
        }
        $this->assertKeySavedCorrectly();
        $this->assertEquals(self::$acsfActiveUser, $this->cloudCredentials->getCloudKey());
        $this->assertEquals(self::$acsfKey, $this->cloudCredentials->getCloudSecret());
        $this->assertEquals(self::$acsfCurrentFactoryUrl, $this->cloudCredentials->getBaseUri());
    }

    /**
     * @return string[][]
     */
    public static function providerTestAcsfAuthLoginInvalid(): array
    {
        return [
            [
                ['--factory-url' => 'example.com', '--key' => 'asdfasdfasdf','--username' => 'asdf'],
                'This value is not a valid URL.',
            ],
            [
                ['--factory-url' => 'https://example.com', '--key' => 'asdf','--username' => 'asdf'],
                'This value is too short. It should have 10 characters or more.',
            ],
        ];
    }

    /**
     * @dataProvider providerTestAcsfAuthLoginInvalid
     * @throws \Exception
     */
    public function testAcsfAuthLoginInvalid(array $args, string $message): void
    {
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->createDataStores();
        $this->command = $this->createCommand();
        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage($message);
        $this->executeCommand($args);
    }

    /**
     * @return string[][]
     */
    public static function providerTestAcsfAuthLoginInvalidInput(): array
    {
        return [
            [
                ['Enter a new factory URL', 'example.com'],
            ],
            [
                ['Enter a new factory URL', ''],
            ],
        ];
    }

    /**
     * @dataProvider providerTestAcsfAuthLoginInvalidInput
     * @throws \JsonException
     */
    public function testAcsfAuthLoginInvalidInput(array $input): void
    {
        $this->removeMockCloudConfigFile();
        $this->createMockCloudConfigFile(AcsfCommandTestBase::getAcsfCredentialsFileContents());
        $this->createDataStores();
        $this->command = $this->createCommand();
        $this->expectException(MissingInputException::class);
        $this->executeCommand([], $input);
    }

    protected function assertKeySavedCorrectly(): void
    {
        $credsFile = $this->cloudConfigFilepath;
        $this->assertFileExists($credsFile);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $credsFile);
        $this->assertTrue($config->exists('acsf_active_factory'));
        $factoryUrl = $config->get('acsf_active_factory');
        $this->assertTrue($config->exists('acsf_factories'));
        $factories = $config->get('acsf_factories');
        $this->assertArrayHasKey($factoryUrl, $factories);
        $factory = $factories[$factoryUrl];
        $this->assertArrayHasKey('users', $factory);
        $this->assertArrayHasKey('active_user', $factory);
        $this->assertEquals(self::$acsfUsername, $factory['active_user']);
        $users = $factory['users'];
        $this->assertArrayHasKey($factory['active_user'], $users);
        $user = $users[$factory['active_user']];
        $this->assertArrayHasKey('username', $user);
        $this->assertArrayHasKey('key', $user);
        $this->assertEquals(self::$acsfUsername, $user['username']);
        $this->assertEquals(self::$acsfKey, $user['key']);
    }
}
