<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\AuthCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * Class AuthCommandTest.
 *
 * @property AuthCommand $command
 * @package Acquia\Cli\Tests
 */
class AuthCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(AuthCommand::class);
  }

  public function providerTestAuthLoginCommand(): array {
    $key = 'testkey123123';
    $secret = 'testsecret123123';
    return [
      [
        FALSE,
        [
          // Do you want to open this page to generate a token now?
          'no',
          // Please enter your API Key:
          $key,
          // Please enter your API Secret:
          $secret,
        ],
        // No arguments, all interactive.
        []
      ],
      [
        TRUE,
        [
          // Your machine already has already been authenticated with Acquia Cloud API, would you like to re-authenticate?
          'yes',
          // Do you want to open this page to generate a token now?
          'no',
          // Please enter your API Key:
          $key,
          // Please enter your API Secret:
          $secret,
        ],
        // No arguments, all interactive.
        []
      ],
      [
        FALSE,
        // No interaction
        [],
        // Args.
        ['--key' => $key, '--secret' => $secret]
      ],
    ];
  }

  /**
   * Tests the 'auth:login' command.
   *
   * @dataProvider providerTestAuthLoginCommand
   *
   * @param $inputs
   * @param $args
   *
   * @throws \Exception
   */
  public function testAuthLoginCommand($machine_is_authenticated, $inputs, $args): void {
    if (!$machine_is_authenticated) {
      $this->removeMockCloudConfigFile();
    }

    $this->executeCommand($args, $inputs);
    $output = $this->getDisplay();

    // Assert.
    if (!array_key_exists('--key', $args)) {
      $this->assertInteractivePrompts($output);
    }
    $this->assertSavedOutput($output);
    $this->assertKeySavedCorrectly();
  }

  public function providerTestAuthLoginInvalidInputCommand(): array {
    $secret = 'testsecret123123';
    return [
      [
        [],
        ['--key' => 'no spaces are allowed' , '--secret' => $secret]
      ],
      [
        [],
        ['--key' => 'shorty' , '--secret' => $secret]
      ],
      [
        [],
        ['--key' => ' ', '--secret' => $secret]
      ],
    ];
  }

  /**
   * Tests the 'auth:login' command.
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

  /**
   * @param string $output
   */
  protected function assertInteractivePrompts(string $output): void {
    $this->assertStringContainsString('You will need an Acquia Cloud API token from https://cloud.acquia.com/a/profile/tokens',
      $output);
    $this->assertStringContainsString('You should create a new token specifically for Developer Studio and enter the associated key and secret below.',
      $output);
    $this->assertStringContainsString('Do you want to open this page to generate a token now?', $output);
    $this->assertStringContainsString('Please enter your API Key:', $output);
    $this->assertStringContainsString('Please enter your API Secret:', $output);
  }

  protected function assertKeySavedCorrectly(): void {
    $creds_file = $this->cloudConfigFilepath;
    $this->assertFileExists($creds_file);
    $contents = file_get_contents($creds_file);
    $this->assertJson($contents);
    $config = json_decode($contents, TRUE);
    $this->assertArrayHasKey('key', $config);
    $this->assertArrayHasKey('secret', $config);
    $this->assertEquals('testkey123123', $config['key']);
    $this->assertEquals('testsecret123123', $config['secret']);
  }

  /**
   * @param string $output
   */
  protected function assertSavedOutput(string $output): void {
    $this->assertStringContainsString('Saved credentials to ', $output);
    $this->assertStringContainsString('/cloud_api.conf', $output);
  }

}
