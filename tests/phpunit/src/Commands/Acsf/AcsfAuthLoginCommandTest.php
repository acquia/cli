<?php

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\Command\Acsf\AcsfApiAuthLoginCommand;
use Acquia\Cli\Command\Auth\AuthLoginCommand;
use Acquia\Cli\Tests\CommandTestBase;
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
class AcsfAuthLoginCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(AcsfApiAuthLoginCommand::class);
  }

  public function providerTestAuthLoginCommand(): array {
    return [
      [
        // $machine_is_authenticated
        FALSE,
        [
          // Would you like to share anonymous performance usage and data? (yes/no) [yes]
          'yes',
          // Enter the full URL of the factory
          'https://www.test.com',
          // Please enter a value for username
          $this->key,
          //  Please enter a value for password
          $this->secret,
        ],
        // No arguments, all interactive.
        [],
        // Output to assert.
        'Saved credentials to',
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
   * @throws \Exception
   */
  public function testAcsfAuthLoginCommand($machine_is_authenticated, $inputs, $args, $output_to_assert): void {
    if (!$machine_is_authenticated) {
      $this->clientServiceProphecy->isMachineAuthenticated(Argument::type(JsonFileStore::class))->willReturn(FALSE);
      $this->removeMockCloudConfigFile();
    }

    $this->executeCommand($args, $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString($output_to_assert, $output);
    $this->assertKeySavedCorrectly();
  }

  public function providerTestAuthLoginInvalidInputCommand(): array {
    return [
      [
        [],
        ['--username' => 'no spaces are allowed' , '--password' => $this->secret]
      ],
      [
        [],
        ['--username' => 'shorty' , '--password' => $this->secret]
      ],
      [
        [],
        ['--username' => ' ', '--password' => $this->secret]
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
    $this->assertTrue($config->exists('acsf_factory'));
    $factory_url = $config->get('acsf_factory');
    $this->assertTrue($config->exists('acsf_keys'));
    $factories = $config->get('acsf_keys');
    $this->assertArrayHasKey($factory_url, $factories);
    $factory = $factories[$factory_url];
    $this->assertArrayHasKey('users', $factory);
    $this->assertArrayHasKey('url', $factory);
    $this->assertArrayHasKey('active_user', $factory);
    $this->assertEquals($this->key, $factory['active_user']);
    $users = $factory['users'];
    $this->assertArrayHasKey($factory['active_user'], $users);
    $user = $users[$factory['active_user']];
    $this->assertArrayHasKey('username', $user);
    $this->assertArrayHasKey('password', $user);
    $this->assertEquals($this->key, $user['username']);
    $this->assertEquals($this->secret, $user['password']);
  }

}
