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

    public function testAuthLogoutInvalidDatastore(): void
    {
        $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(false);
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
