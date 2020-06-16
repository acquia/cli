<?php

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\Command\Auth\AuthLogoutCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;

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
      $this->removeMockCloudConfigFile();
    }

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    // Assert creds are removed locally.
    $this->assertFileExists($this->cloudConfigFilepath);
    $contents = file_get_contents($this->cloudConfigFilepath);
    $this->assertJson($contents);
    $config = json_decode($contents, TRUE);
    $this->assertNull($config['key']);
    $this->assertNull($config['secret']);
  }

}
