<?php

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Command\Acsf\AcsfApiAuthLoginCommand;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Symfony\Component\Console\Command\Command;

/**
 * Class AuthCommandTest.
 *
 * @property \Acquia\Cli\Command\Auth\AuthLoginCommand $command
 * @package Acquia\Cli\Tests
 */
class AcsfAuthLoginCommandTest extends AcsfCommandTestBase {

  protected function createCommand(): Command {
    $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
    return $this->injectCommand(AcsfApiAuthLoginCommand::class);
  }

  public function providerTestAuthLoginCommand(): array {
    return [
      // Data set 0.
      [
        // $machine_is_authenticated
        FALSE,
        // $inputs
        [
          // Would you like to share anonymous performance usage and data? (yes/no) [yes]
          'yes',
          // Enter the full URL of the factory
          $this->acsfCurrentFactoryUrl,
          // Enter a value for username
          $this->acsfUsername,
          //  Enter a value for key
          $this->acsfKey,
        ],
        // No arguments, all interactive.
        [],
        // Output to assert.
        'Saved credentials',
      ],
      // Data set 1.
      [
        // $machine_is_authenticated
        FALSE,
        // $inputs
        [],
        // Arguments.
        [
          // Enter the full URL of the factory
          '--factory-url' => $this->acsfCurrentFactoryUrl,
          // Enter a value for username
          '--username' => $this->acsfUsername,
          //  Enter a value for key
          '--key' => $this->acsfKey,
        ],
        // Output to assert.
        'Saved credentials',
        // $config.
        $this->getAcsfCredentialsFileContents(),
      ],
      // Data set 2.
      [
        // $machine_is_authenticated
        TRUE,
        // $inputs
        [
          // Choose a factory to log in to.
          $this->acsfCurrentFactoryUrl,
          // Choose which user to log in as.
          $this->acsfUsername,
        ],
        // Arguments.
        [],
        // Output to assert.
        "Acquia CLI is now logged in to {$this->acsfCurrentFactoryUrl} as {$this->acsfUsername}",
        // $config.
        $this->getAcsfCredentialsFileContents(),
      ],
    ];
  }

  /**
   * Tests the 'acsf:auth:login' command.
   *
   * @dataProvider providerTestAuthLoginCommand
   * @param $machine_is_authenticated
   * @param $inputs
   * @param $args
   * @param $output_to_assert
   * @param array $config
   * @throws \Exception
   * @requires OS linux|darwin
   */
  public function testAcsfAuthLoginCommand($machine_is_authenticated, $inputs, $args, $output_to_assert, array $config = []): void {
    if (!$machine_is_authenticated) {
      $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
      $this->removeMockCloudConfigFile();
    }
    else {
      $this->removeMockCloudConfigFile();
      $this->createMockCloudConfigFile($config);
    }
    $this->createDataStores();
    $this->command = $this->createCommand();

    $this->executeCommand($args, $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString($output_to_assert, $output);
    if (!$machine_is_authenticated && !array_key_exists('--key', $args)) {
      $this->assertStringContainsString('Enter your Site Factory key (option -k, --key) (input will be hidden):', $output);
    }
    $this->assertKeySavedCorrectly();
    $this->assertEquals($this->acsfActiveUser, $this->cloudCredentials->getCloudKey());
    $this->assertEquals($this->acsfKey, $this->cloudCredentials->getCloudSecret());
    $this->assertEquals($this->acsfCurrentFactoryUrl, $this->cloudCredentials->getBaseUri());

  }

  protected function assertKeySavedCorrectly(): void {
    $creds_file = $this->cloudConfigFilepath;
    $this->assertFileExists($creds_file);
    $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $creds_file);
    $this->assertTrue($config->exists('acsf_active_factory'));
    $factory_url = $config->get('acsf_active_factory');
    $this->assertTrue($config->exists('acsf_factories'));
    $factories = $config->get('acsf_factories');
    $this->assertArrayHasKey($factory_url, $factories);
    $factory = $factories[$factory_url];
    $this->assertArrayHasKey('users', $factory);
    $this->assertArrayHasKey('active_user', $factory);
    $this->assertEquals($this->acsfUsername, $factory['active_user']);
    $users = $factory['users'];
    $this->assertArrayHasKey($factory['active_user'], $users);
    $user = $users[$factory['active_user']];
    $this->assertArrayHasKey('username', $user);
    $this->assertArrayHasKey('key', $user);
    $this->assertEquals($this->acsfUsername, $user['username']);
    $this->assertEquals($this->acsfKey, $user['key']);
  }

}
