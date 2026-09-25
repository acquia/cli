<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\Command\Auth\AuthLogoutCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property AuthLogoutCommandTest $command
 */
class AuthLogoutCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(AuthLogoutCommand::class);
    }

    public function testAuthLogoutCommand(): void
    {
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertFileExists($this->cloudConfigFilepath);
        $this->assertStringContainsString('The key Test Key will be deactivated on this machine.', $output);
        $this->assertStringContainsString('Do you want to delete the active Cloud Platform API credentials (option --delete)? (yes/no) [no]:', $output);
        $this->assertStringContainsString('The active Cloud Platform API credentials were deactivated', $output);
    }

    public function testAuthLogoutWithDeviceTokenOnly(): void
    {
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode([
            'device_token' => [
                'access_token' => 'existing-token',
                'client_id' => 'client-123',
                'expiry' => time() + 300,
                'refresh_token' => 'existing-refresh-token',
            ],
            'send_telemetry' => false,
        ]));
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand();
        $output = $this->getDisplay();

        $this->assertStringContainsString('Device code session removed', $output);
        $this->assertStringContainsString('No Cloud Platform credentials are active', $output);
        $this->assertStringNotContainsString('will be deactivated on this machine', $output);
        $this->assertArrayNotHasKey('device_token', json_decode(file_get_contents($this->cloudConfigFilepath), true));
    }

    public function testAuthLogoutRemovesBothCredentialTypes(): void
    {
        $this->executeCommandWithDeviceTokenAlongsideKey();
        $output = $this->getDisplay();

        $this->assertStringContainsString('Device code session removed', $output);
        $this->assertStringContainsString('The key Test Key will be deactivated on this machine.', $output);
        $this->assertStringContainsString('The active Cloud Platform API credentials were deactivated', $output);

        $config = json_decode(file_get_contents($this->cloudConfigFilepath), true);
        $this->assertArrayNotHasKey('device_token', $config);
        $this->assertArrayNotHasKey('acli_key', $config);
    }

    public function testAuthLogoutWithNoCredentialsAtAll(): void
    {
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode(['send_telemetry' => false]));
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('There are no active Cloud Platform credentials');

        $this->executeCommand();
    }

    private function executeCommandWithDeviceTokenAlongsideKey(): void
    {
        $config = json_decode(file_get_contents($this->cloudConfigFilepath), true);
        $config['device_token'] = [
            'access_token' => 'existing-token',
            'client_id' => 'client-123',
            'expiry' => time() + 300,
            'refresh_token' => 'existing-refresh-token',
        ];
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode($config));
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand();
    }

    public function testAuthLogoutInvalidDatastore(): void
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
}
