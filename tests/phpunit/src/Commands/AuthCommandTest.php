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

  /**
   * Tests the 'auth:login' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testAuthLoginCommand(): void {
    $this->setCommand($this->createCommand());

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
    $this->assertStringContainsString('Saved credentials. ', $output);

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
