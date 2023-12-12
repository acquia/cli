<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Command\Auth\AuthAcsfLoginCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;

/**
 * @property \Acquia\Cli\Command\Auth\AuthLoginCommand $command
 */
class AcsfAuthLoginCommandTest extends AcsfCommandTestBase {

  protected function createCommand(): CommandBase {
    $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
    return $this->injectCommand(AuthAcsfLoginCommand::class);
  }

  /**
   * @return array<mixed>
   */
  public function providerTestAuthLoginCommand(): array {
    return [
      // Data set 0.
      [
        // $machineIsAuthenticated
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
        // $machineIsAuthenticated
        FALSE,
        // $inputs
        [],
        // Arguments.
        [
          // Enter the full URL of the factory
          '--factory-url' => $this->acsfCurrentFactoryUrl,
          //  Enter a value for key
          '--key' => $this->acsfKey,
          // Enter a value for username
          '--username' => $this->acsfUsername,
        ],
        // Output to assert.
        'Saved credentials',
        // $config.
        $this->getAcsfCredentialsFileContents(),
      ],
      // Data set 2.
      [
        // $machineIsAuthenticated
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
        "Acquia CLI is now logged in to $this->acsfCurrentFactoryUrl as $this->acsfUsername",
        // $config.
        $this->getAcsfCredentialsFileContents(),
      ],
    ];
  }

  /**
   * @dataProvider providerTestAuthLoginCommand
   * @requires OS linux|darwin
   */
  public function testAcsfAuthLoginCommand(bool $machineIsAuthenticated, array $inputs, array $args, string $outputToAssert, array $config = []): void {
    if (!$machineIsAuthenticated) {
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
    $this->assertStringContainsString($outputToAssert, $output);
    if (!$machineIsAuthenticated && !array_key_exists('--key', $args)) {
      $this->assertStringContainsString('Enter your Site Factory key (option -k, --key) (input will be hidden):', $output);
    }
    $this->assertKeySavedCorrectly();
    $this->assertEquals($this->acsfActiveUser, $this->cloudCredentials->getCloudKey());
    $this->assertEquals($this->acsfKey, $this->cloudCredentials->getCloudSecret());
    $this->assertEquals($this->acsfCurrentFactoryUrl, $this->cloudCredentials->getBaseUri());

  }

  protected function assertKeySavedCorrectly(): void {
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
