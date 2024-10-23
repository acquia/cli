<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\Command\Auth\AuthLoginCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Connector;
use Generator;
use Prophecy\Argument;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property AuthLoginCommand $command
 */
class AuthLoginCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(AuthLoginCommand::class);
    }

    public function testAuthLoginCommand(): void
    {
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand([
            '--key' => self::$key,
            '--secret' => self::$secret,
        ]);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $this->assertKeySavedCorrectly();
    }

    public function testAuthLoginNoKeysCommand(): void
    {
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode(['send_telemetry' => false]));
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand([
            '--key' => self::$key,
            '--secret' => self::$secret,
        ]);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $this->assertKeySavedCorrectly();
    }

    public static function providerTestAuthLoginInvalidInputCommand(): Generator
    {
        yield
        [
            [],
            ['--key' => 'no spaces are allowed', '--secret' => self::$secret],
        ];
        yield
        [
            [],
            ['--key' => 'shorty', '--secret' => self::$secret],
        ];
        yield
        [
            [],
            ['--key' => ' ', '--secret' => self::$secret],
        ];
    }

    /**
     * @dataProvider providerTestAuthLoginInvalidInputCommand
     */
    public function testAuthLoginInvalidInputCommand(array $inputs, array $args): void
    {
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->createDataStores();
        $this->command = $this->createCommand();
        $this->expectException(ValidatorException::class);
        $this->executeCommand($args, $inputs);
    }

    public function testAuthLoginInvalidDatastore(): void
    {
        $this->clientServiceProphecy->isMachineAuthenticated()
            ->willReturn(false);
        $this->removeMockCloudConfigFile();
        $data = [
            'acli_key' => 'key2',
            'keys' => [
                'key1' => [
                    'label' => 'foo',
                    'secret' => 'foo',
                    'uuid' => 'foo',
                ],
            ],
        ];
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode($data));
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage("Configuration file at the following path contains invalid keys: $this->cloudConfigFilepath Invalid configuration for path \"cloud_api\": acli_key must exist in keys");
        $this->createDataStores();
    }

    protected function assertInteractivePrompts(string $output): void
    {
        // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
        $this->assertStringContainsString('You will need a Cloud Platform API token from https://cloud.acquia.com/a/profile/tokens', $output);
        $this->assertStringContainsString('Do you want to open this page to generate a token now?', $output);
        $this->assertStringContainsString('Enter your Cloud API key (option -k, --key):', $output);
        $this->assertStringContainsString('Enter your Cloud API secret (option -s, --secret) (input will be hidden):', $output);
    }

    protected function assertKeySavedCorrectly(): void
    {
        $credsFile = $this->cloudConfigFilepath;
        $this->assertFileExists($credsFile);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $credsFile);
        $this->assertTrue($config->exists('acli_key'));
        $this->assertEquals(self::$key, $config->get('acli_key'));
        $this->assertTrue($config->exists('keys'));
        $keys = $config->get('keys');
        $this->assertArrayHasKey(self::$key, $keys);
        $this->assertArrayHasKey('label', $keys[self::$key]);
        $this->assertArrayHasKey('secret', $keys[self::$key]);
        $this->assertEquals(self::$secret, $keys[self::$key]['secret']);
    }
}
