<?php

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\Command\Auth\AuthLogoutCommand;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class AuthLogoutCommandTest.
 *
 * @property AuthLogoutCommandTest $command
 * @package Acquia\Cli\Tests
 */
class AuthLogoutCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(AuthLogoutCommand::class);
  }

  public function providerTestAuthLogoutCommand(): array {
    return [
      [FALSE, []],
      [
        TRUE,
        // Are you sure you'd like to remove your Cloud API login credentials from this machine?
        ['y'],
      ]
    ];
  }

  /**
   * Tests the 'auth:login' command.
   *
   * @dataProvider providerTestAuthLogoutCommand
   *
   * @param bool $machine_is_authenticated
   * @param array $inputs
   *
   * @throws \Exception
   */
  public function testAuthLogoutCommand($machine_is_authenticated, $inputs): void {
    if (!$machine_is_authenticated) {
      $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
      $this->removeMockCloudConfigFile();
    }

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    // Assert creds are removed locally.
    $this->assertFileExists($this->cloudConfigFilepath);
    $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
    $this->assertFalse($config->exists('acli_key'));
  }

}
