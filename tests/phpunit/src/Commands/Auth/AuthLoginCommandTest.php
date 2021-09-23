<?php

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\Command\Auth\AuthLoginCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * Class AuthCommandTest.
 *
 * @property AuthLoginCommand $command
 * @package Acquia\Cli\Tests
 */
class AuthLoginCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(AuthLoginCommand::class);
  }

  public function providerTestAuthLoginCommand(): array {
    return [
      [
        // $machine_is_authenticated
        FALSE,
        // $assert_cloud_prompts
        TRUE,
        [
          // Would you like to share anonymous performance usage and data? (yes/no) [yes]
          'yes',
          // Do you want to open this page to generate a token now?
          'no',
          // Please enter your API Key:
          $this->key,
          // Please enter your API Secret:
          $this->secret,
        ],
        // No arguments, all interactive.
        [],
        // Output to assert.
        'Saved credentials to',
      ],
      [
        // $machine_is_authenticated
        TRUE,
        // $assert_cloud_prompts
        TRUE,
        [
          // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
          'yes',
          // Choose which API key to use:
          "Create a new API key",
          // Do you want to open this page to generate a token now?
          'no',
          // Please enter your API Key:
          $this->key,
          // Please enter your API Secret:
          $this->secret,
        ],
        // No arguments, all interactive.
        [],
        // Output to assert.
        'Saved credentials to',
      ],
      [
        // $machine_is_authenticated
        TRUE,
        // $assert_cloud_prompts
        FALSE,
        [
          // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
          'yes',
          // Choose which API key to use:
          'Test Key',
          // @todo Make sure this key has the right value to assert.
        ],
        // No arguments, all interactive.
        [],
        // Output to assert.
        'Acquia CLI will use the API Key',
      ],
      [
        // $machine_is_authenticated
        FALSE,
        // $assert_cloud_prompts
        FALSE,
        // No interaction
        [],
        // Args.
        ['--key' => $this->key, '--secret' => $this->secret],
        // Output to assert.
        'Saved credentials to',
      ],
    ];
  }

  /**
   * Tests the 'auth:login' command.
   *
   * @dataProvider providerTestAuthLoginCommand
   *
   * @param $machine_is_authenticated
   * @param $assert_cloud_prompts
   * @param $inputs
   * @param $args
   *
   * @throws \Exception
   */
  public function testAuthLoginCommand($machine_is_authenticated, $assert_cloud_prompts, $inputs, $args, $output_to_assert): void {
    $mock_body = $this->mockTokenRequest();
    if (!$machine_is_authenticated) {
      $this->removeMockCloudConfigFile();
    }

    $this->executeCommand($args, $inputs);
    $output = $this->getDisplay();

    if ($assert_cloud_prompts) {
      $this->assertInteractivePrompts($output);
    }
    $this->assertStringContainsString($output_to_assert, $output);
    $this->assertKeySavedCorrectly();
  }

  public function providerTestAuthLoginInvalidInputCommand(): array {
    return [
      [
        [],
        ['--key' => 'no spaces are allowed' , '--secret' => $this->secret]
      ],
      [
        [],
        ['--key' => 'shorty' , '--secret' => $this->secret]
      ],
      [
        [],
        ['--key' => ' ', '--secret' => $this->secret]
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
  public function testAuthLoginInvalidInputCommand($inputs, $args): void {
    $this->removeMockCloudConfigFile();
    try {
      $this->executeCommand($args, $inputs);
    }
    catch (ValidatorException $exception) {
      $this->assertEquals(ValidatorException::class, get_class($exception));
    }
  }

  public function testMigrateLegacyApiKey() {
    $mock_body = $this->mockTokenRequest();
    $this->removeMockCloudConfigFile();
    $this->createMockCloudConfigFile([
      'key' => $mock_body->uuid,
      'secret' => 'test',
      DataStoreContract::SEND_TELEMETRY => FALSE,
    ]);
    $inputs = [
      // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
      'n',
    ];
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString('Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?', $output);
  }

  /**
   * @param string $output
   */
  protected function assertInteractivePrompts(string $output): void {
    // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
    $this->assertStringContainsString('You will need a Cloud Platform API token from https://cloud.acquia.com/a/profile/tokens', $output);
    $this->assertStringContainsString('Do you want to open this page to generate a token now?', $output);
    $this->assertStringContainsString('Please enter your API Key:', $output);
    $this->assertStringContainsString('Please enter your API Secret', $output);
  }

  protected function assertKeySavedCorrectly(): void {
    $creds_file = $this->cloudConfigFilepath;
    $this->assertFileExists($creds_file);
    $config = new JsonFileStore($creds_file, JsonFileStore::NO_SERIALIZE_STRINGS);
    $this->assertTrue($config->exists('acli_key'));
    $this->assertEquals($this->key, $config->get('acli_key'));
    $this->assertTrue($config->exists('keys'));
    $keys = $config->get('keys');
    $this->assertArrayHasKey($this->key, $keys);
    $this->assertArrayHasKey('uuid', $keys[$this->key]);
    $this->assertArrayHasKey('label', $keys[$this->key]);
    $this->assertArrayHasKey('secret', $keys[$this->key]);
    $this->assertEquals($this->secret, $keys[$this->key]['secret']);
  }

  /**
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockTokenRequest() {
    $mock_body = $this->getMockResponseFromSpec('/account/tokens/{tokenUuid}',
      'get', '200');
    $this->clientProphecy->request('get', "/account/tokens/{$this->key}")
      ->willReturn($mock_body);
    return $mock_body;
  }

}
