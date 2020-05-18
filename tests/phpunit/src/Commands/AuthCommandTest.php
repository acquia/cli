<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\AuthCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class IdeDeleteCommandTest.
 *
 * @property AuthCommand $command
 * @package Acquia\Cli\Tests
 */
class AuthCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new AuthCommand();
  }

  public function providerTestAuthLoginCommand(): array {
    $key = 'testkey123123';
    $secret = 'testsecret123123';
    return [
      [
        [
        // Do you want to open this page to generate a token now?
        'no',
        // Please enter your API Key:
        $key,
        // Please enter your API Secret:
        $secret,
        ],
        []
      ],
      [
        [],
        ['--key' => $key, '--secret' => $secret]
      ],
    ];
  }

  /**
   * Tests the 'auth:login' command.
   *
   * @dataProvider providerTestAuthLoginCommand
   *
   * @throws \Exception
   */
  public function testAuthLoginCommand($inputs, $args): void {
    $this->setCommand($this->createCommand());

    $this->executeCommand($args, $inputs);
    $output = $this->getDisplay();

    // Assert.
    if (!array_key_exists('--key', $args)) {
      $this->assertStringContainsString('You will need an Acquia Cloud API token from https://cloud.acquia.com/a/profile/tokens.',
        $output);
      $this->assertStringContainsString('You should create a new token specifically for Developer Studio and enter the associated key and secret below.',
        $output);
      $this->assertStringContainsString('Do you want to open this page to generate a token now?', $output);
      $this->assertStringContainsString('Please enter your API Key:', $output);
      $this->assertStringContainsString('Please enter your API Secret:', $output);
    }
    $this->assertStringContainsString('Saved credentials to ', $output);
    $this->assertStringContainsString('/cloud_api.conf', $output);

    $creds_file = $this->application->getCloudConfigFilepath();
    $this->assertFileExists($creds_file);
    $contents = file_get_contents($creds_file);
    $this->assertJson($contents);
    $config = json_decode($contents, TRUE);
    $this->assertArrayHasKey('key', $config);
    $this->assertArrayHasKey('secret', $config);
    $this->assertEquals('testkey123123', $config['key']);
    $this->assertEquals('testsecret123123', $config['secret']);
  }

}
