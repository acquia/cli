<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Command\Auth\AuthAcsfLogoutCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;

/**
 * @property AcsfAuthLogoutCommandTest $command
 */
class AcsfAuthLogoutCommandTest extends AcsfCommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
        return $this->injectCommand(AuthAcsfLogoutCommand::class);
    }

    /**
     * @return array<mixed>
     */
    public function providerTestAuthLogoutCommand(): array
    {
        return [
        // Data set 0.
            [
        // $machineIsAuthenticated
                false,
        // $inputs
                [],
            ],
            // Data set 1.
            [
            // $machineIsAuthenticated
                true,
            // $inputs
                [
            // Choose a Factory to logout of.
                    0,
                ],
                // $config.
                $this->getAcsfCredentialsFileContents(),
            ],
        ];
    }

    /**
     * @dataProvider providerTestAuthLogoutCommand
     */
    public function testAcsfAuthLogoutCommand(bool $machineIsAuthenticated, array $inputs, array $config = []): void
    {
        if (!$machineIsAuthenticated) {
            $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(false);
            $this->removeMockCloudConfigFile();
        } else {
            $this->createMockCloudConfigFile($config);
        }

        $this->createDataStores();
        $this->command = $this->createCommand();
        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();
        // Assert creds are removed locally.
        $this->assertFileExists($this->cloudConfigFilepath);
        $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
        $this->assertFalse($config->exists('acli_key'));
        $this->assertNull($config->get('acsf_active_factory'));
    }
}
