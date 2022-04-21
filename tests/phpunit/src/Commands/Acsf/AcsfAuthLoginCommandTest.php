<?php

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Command\Acsf\AcsfApiAuthLoginCommand;
use Acquia\Cli\Command\Auth\AuthLoginCommand;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * Class AuthCommandTest.
 *
 * @property AuthLoginCommand $command
 * @package Acquia\Cli\Tests
 */
class AcsfAuthLoginCommandTest extends AcsfCommandTestBase {

  /**
   * {@inheritdoc}
   */
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
          // Please enter a value for username
          $this->acsfUsername,
          //  Please enter a value for password
          $this->acsfPassword,
        ],
        // No arguments, all interactive.
        [],
        // Output to assert.
        'Saved credentials to',
      ],
      // Data set 1.
      [
        // $machine_is_authenticated
        FALSE,
        // $inputs
        [
          // Would you like to share anonymous performance usage and data? (yes/no) [yes]
          'yes',
        ],
        // Arguments.
        [
          // Enter the full URL of the factory
          '--factory-url' => $this->acsfCurrentFactoryUrl,
          // Please enter a value for username
          '--username' => $this->acsfUsername,
          //  Please enter a value for password
          '--password' => $this->acsfPassword,
        ],
        // Output to assert.
        'Saved credentials to',
        // $config.
        $this->getAcsfCredentialsFileContents(),
      ],
      // Data set 2.
      [
        // $machine_is_authenticated
        TRUE,
        // $inputs
        [
          // Please choose a factory to login to.
          $this->acsfCurrentFactoryUrl,
          // Choose which user to login as.
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
   *
   * @param $machine_is_authenticated
   * @param $inputs
   * @param $args
   * @param $output_to_assert
   *
   * @requires OS linux|darwin
   * @throws \Exception
   */
  public function testAcsfAuthLoginCommand($machine_is_authenticated, $inputs, $args, $output_to_assert, $config = []): void {
    if (!$machine_is_authenticated) {
      $this->clientServiceProphecy->isMachineAuthenticated(Argument::type(JsonFileStore::class))->willReturn(FALSE);
      $this->removeMockCloudConfigFile();
    }
    else {
      $this->createMockCloudConfigFile($config);
    }

    $this->executeCommand($args, $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString($output_to_assert, $output);
    $this->assertKeySavedCorrectly();
    if ($machine_is_authenticated) {
      $this->assertEquals($this->acsfActiveUser, $this->cloudCredentials->getCloudKey());
      $this->assertEquals($this->acsfPassword, $this->cloudCredentials->getCloudSecret());
      $this->assertEquals($this->acsfCurrentFactoryUrl, $this->cloudCredentials->getBaseUri());
    }
  }

  public function providerTestAuthLoginInvalidInputCommand(): array {
    return [
      [
        [],
        ['--username' => 'no spaces are allowed' , '--password' => $this->acsfPassword]
      ],
      [
        [],
        ['--username' => 'shorty' , '--password' => $this->acsfPassword]
      ],
      [
        [],
        ['--username' => ' ', '--password' => $this->acsfPassword]
      ],
    ];
  }

  /**
   * Tests the 'auth:login' command with invalid input.
   *
   * @dataProvider providerTestAuthLoginInvalidInputCommand
   *
   * @param $inputs
   * @param $args
   * @throws \Exception
   */
  public function testAcsfAuthLoginInvalidInputCommand($inputs, $args): void {
    $this->clientServiceProphecy->isMachineAuthenticated(Argument::type(JsonFileStore::class))->willReturn(FALSE);
    $this->removeMockCloudConfigFile();
    try {
      $this->executeCommand($args, $inputs);
    }
    catch (ValidatorException $exception) {
      $this->assertEquals(ValidatorException::class, get_class($exception));
    }
  }

  /**
   *
   */
  protected function assertKeySavedCorrectly(): void {
    $creds_file = $this->cloudConfigFilepath;
    $this->assertFileExists($creds_file);
    $config = new JsonFileStore($creds_file, JsonFileStore::NO_SERIALIZE_STRINGS);
    $this->assertTrue($config->exists('acsf_active_factory'));
    $factory_url = $config->get('acsf_active_factory');
    $this->assertTrue($config->exists('acsf_factories'));
    $factories = $config->get('acsf_factories');
    $this->assertArrayHasKey($factory_url, $factories);
    $factory = $factories[$factory_url];
    $this->assertArrayHasKey('users', $factory);
    $this->assertArrayHasKey('url', $factory);
    $this->assertArrayHasKey('active_user', $factory);
    $this->assertEquals($this->acsfUsername, $factory['active_user']);
    $users = $factory['users'];
    $this->assertArrayHasKey($factory['active_user'], $users);
    $user = $users[$factory['active_user']];
    $this->assertArrayHasKey('username', $user);
    $this->assertArrayHasKey('password', $user);
    $this->assertEquals($this->acsfUsername, $user['username']);
    $this->assertEquals($this->acsfPassword, $user['password']);
  }

}
