<?php

namespace Acquia\Ads\Tests\Commands;

use Acquia\Ads\Command\AuthCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeDeleteCommandTest.
 *
 * @property AuthCommand $command
 * @package Acquia\Ads\Tests
 */
class AuthCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new AuthCommand();
  }

  /**
   * Tests the 'auth:login' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testAuthLoginCommand(): void {
    $this->setCommand($this->createCommand());
    $this->command->setCloudApiConfFilePath(sys_get_temp_dir() . '/cloud_api.conf');

    $inputs = [
          // Do you want to open this page to generate a token now?
      'no',
          // Please enter your API Key:
      'testkey123123',
          // Please enter your API Secret:
      'testsecret123123',
    ];

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();

    // Assert.
    $this->assertStringContainsString('You will need an Acquia Cloud API token from https://cloud.acquia.com/a/profile/tokens.', $output);
    $this->assertStringContainsString('You should create a new token specifically for Developer Studio and enter the associated key and secret below.', $output);
    $this->assertStringContainsString('Do you want to open this page to generate a token now?', $output);
    $this->assertStringContainsString('Please enter your API Key:', $output);
    $this->assertStringContainsString('Please enter your API Secret:', $output);
    $this->assertStringContainsString('Saved credentials to ', $output);
    $this->assertStringContainsString('/cloud_api.conf', $output);

    $creds_file = $this->command->getCloudApiConfFilePath();
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
