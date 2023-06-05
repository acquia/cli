<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\Command\Auth\AuthLoginCommand;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\CommandTestBase;
use Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property AuthLoginCommand $command
 */
class AuthLoginCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(AuthLoginCommand::class);
  }

  public function providerTestAuthLoginCommand(): Generator {
    yield 'Interactive keys' => [
        // $machineIsAuthenticated
        FALSE,
        // $assertCloudPrompts
        TRUE,
        [
          // Would you like to share anonymous performance usage and data? (yes/no) [yes]
          'yes',
          // Do you want to open this page to generate a token now?
          'no',
          // Enter your API Key:
          $this->key,
          // Enter your API Secret:
          $this->secret,
        ],
        // No arguments, all interactive.
        [],
        // Output to assert.
        'Saved credentials',
      ];
    yield 'Already authenticated, enter new key' => [
        // $machineIsAuthenticated
        TRUE,
        // $assertCloudPrompts
        TRUE,
        [
          // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
          'yes',
          // Choose which API key to use:
          "Enter a new API key",
          // Do you want to open this page to generate a token now?
          'no',
          // Enter your API Key:
          $this->key,
          // Enter your API Secret:
          $this->secret,
        ],
        // No arguments, all interactive.
        [],
        // Output to assert.
        'Saved credentials',
      ];
    yield 'Already authenticated, use existing key' => [
        // $machineIsAuthenticated
        TRUE,
        // $assertCloudPrompts
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
      ];
    yield 'Already authenticated, abort' => [
        // $machineIsAuthenticated
        TRUE,
        // $assertCloudPrompts
        FALSE,
        [
          // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
          'no',
        ],
        // No arguments, all interactive.
        [],
        // Output to assert.
        'Your machine has already been authenticated',
      ];
    yield 'Keys as args' => [
        // $machineIsAuthenticated
        FALSE,
        // $assertCloudPrompts
        FALSE,
        // No interaction
        [],
        // Args.
        ['--key' => $this->key, '--secret' => $this->secret],
        // Output to assert.
        'Saved credentials',
    ];
  }

  /**
   * @dataProvider providerTestAuthLoginCommand
   */
  public function testAuthLoginCommand(bool $machineIsAuthenticated, bool $assertCloudPrompts, array $inputs, array $args, string $outputToAssert): void {
    $this->mockRequest('getAccountToken', $this->key);
    if (!$machineIsAuthenticated) {
      $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
      $this->removeMockCloudConfigFile();
      $this->createDataStores();
      $this->command = $this->createCommand();
    }

    $this->executeCommand($args, $inputs);
    $output = $this->getDisplay();

    if ($assertCloudPrompts) {
      $this->assertInteractivePrompts($output);
    }
    $this->assertStringContainsString($outputToAssert, $output);
    $this->assertKeySavedCorrectly();
  }

  public function providerTestAuthLoginInvalidInputCommand(): Generator {
    yield
      [
        [],
        ['--key' => 'no spaces are allowed' , '--secret' => $this->secret],
      ];
    yield
      [
        [],
        ['--key' => 'shorty' , '--secret' => $this->secret],
      ];
    yield
      [
        [],
        ['--key' => ' ', '--secret' => $this->secret],
      ];
  }

  /**
   * @dataProvider providerTestAuthLoginInvalidInputCommand
   */
  public function testAuthLoginInvalidInputCommand(array $inputs, array $args): void {
    $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
    $this->removeMockCloudConfigFile();
    $this->createDataStores();
    $this->command = $this->createCommand();
    $this->expectException(ValidatorException::class);
    $this->executeCommand($args, $inputs);
  }

  protected function assertInteractivePrompts(string $output): void {
    // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
    $this->assertStringContainsString('You will need a Cloud Platform API token from https://cloud.acquia.com/a/profile/tokens', $output);
    $this->assertStringContainsString('Do you want to open this page to generate a token now?', $output);
    $this->assertStringContainsString('Enter your Cloud API key (option -k, --key):', $output);
    $this->assertStringContainsString('Enter your Cloud API secret (option -s, --secret) (input will be hidden):', $output);
  }

  protected function assertKeySavedCorrectly(): void {
    $credsFile = $this->cloudConfigFilepath;
    $this->assertFileExists($credsFile);
    $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $credsFile);
    $this->assertTrue($config->exists('acli_key'));
    $this->assertEquals($this->key, $config->get('acli_key'));
    $this->assertTrue($config->exists('keys'));
    $keys = $config->get('keys');
    $this->assertArrayHasKey($this->key, $keys);
    $this->assertArrayHasKey('label', $keys[$this->key]);
    $this->assertArrayHasKey('secret', $keys[$this->key]);
    $this->assertEquals($this->secret, $keys[$this->key]['secret']);
  }

}
