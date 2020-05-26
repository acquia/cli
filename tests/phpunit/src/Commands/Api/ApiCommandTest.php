<?php

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiCommandBase;
use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class ApiCommandTest.
 *
 * @property ApiCommandBase $command
 * @package Acquia\Cli\Tests\Api
 */
class ApiCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new ApiCommandBase();
  }

  /**
   * Tests the 'api:*' commands.
   */
  public function testApiCommandExecutionForHttpGet(): void {
    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $this->clientProphecy->addQuery('limit', '1')->shouldBeCalled();
    $this->clientProphecy->request('get', '/account/ssh-keys')->willReturn($mock_body->{'_embedded'}->items)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
    // Our mock Client doesn't actually return a limited dataset, but we still assert it was passed added to the
    // client's query correctly.
    $this->executeCommand(['--limit' => '1']);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertNotNull($output);
    $this->assertJson($output);
    $contents = json_decode($output, TRUE);
    $this->assertArrayHasKey(0, $contents);
    $this->assertArrayHasKey('uuid', $contents[0]);
  }

  /**
   *
   */
  public function providerTestApiCommandDefinition(): array {
    return [
          ['0'],
          ['1'],
    ];
  }

  public function testApiCommandExecutionForHttpPost(): void {
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $mock_response_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'post', '202');
    foreach ($mock_request_args as $name => $value) {
      $this->clientProphecy->addOption('form_params', [$name => $value])->shouldBeCalled();
    }
    $this->clientProphecy->request('post', '/account/ssh-keys')->willReturn($mock_response_body)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:accounts:ssh-key-create');
    $this->executeCommand($mock_request_args);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertNotNull($output);
    $this->assertJson($output);
    $this->assertStringContainsString('Adding SSH key.', $output);
  }

  /**
   * @dataProvider providerTestApiCommandDefinition
   * @throws \Psr\Cache\InvalidArgumentException
   * @group noCache
   */
  public function testApiCommandDefinitionForGetEndpoint($use_command_cache): void {
    putenv('ACQUIA_CLI_USE_COMMAND_CACHE=' . $use_command_cache);

    $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
    $resource = $this->getResourceFromSpec('/account/ssh-keys', 'get');
    $this->assertEquals($resource['summary'], $this->command->getDescription());

    $expected_command_name = 'api:' . $resource['x-cli-name'];
    $this->assertEquals($expected_command_name, $this->command->getName());

    foreach ($resource['parameters'] as $parameter) {
      $param_name = strtolower(str_replace('#/components/parameters/', '', $parameter['$ref']));
      $this->assertTrue(
            $this->command->getDefinition()->hasOption($param_name) ||
            $this->command->getDefinition()->hasArgument($param_name),
            "Command $expected_command_name does not have expected argument or option $param_name"
        );
    }
    $this->assertStringContainsString('api:accounts:ssh-keys-list --from="-7d" --to="-1d" --sort="field1,-field2" --limit="10" --offset="10" ', $this->command->getUsages()[0]);
  }

  /**
   * @dataProvider providerTestApiCommandDefinition
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testApiCommandDefinitionForPostEndpoint(): void {
    $this->command = $this->getApiCommandByName('api:accounts:ssh-key-create');
    $resource = $this->getResourceFromSpec('/account/ssh-keys', 'post');
    foreach ($resource['requestBody']['content']['application/json']['example'] as $key => $value) {
      $this->assertTrue(
            $this->command->getDefinition()->hasArgument($key) ||
            $this->command->getDefinition()->hasOption($key),
            "Command {$this->command->getName()} does not have expected argument or option $key"
        );
    }
    $this->assertStringContainsString('api:accounts:ssh-key-create "mykey" "ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEAklOUpkDHrfHY17SbrmTIpNLTGK9Tjom/BWDSUGPl+nafzlHDTYW7hdI4yZ5ew18JH4JW9jbhUFrviQzM7xlELEVf4h9lFX5QVkbPppSwg0cda3Pbv7kOdJ/MTyBlWXFCR+HAo3FXRitBqxiX1nKhXpHAZsMciLq8V6RjsNAQwdsdMFvSlVK/7XAt3FaoJoAsncM1Q9x5+3V0Ww68/eIFmb1zuUFljQJKprrX88XypNDvjYNby6vw/Pb0rwert/EnmZ+AW4OZPnTPI89ZPmVMLuayrD2cE86Z/il8b+gw3r3+1nKatmIkjn2so1d01QraTlMqVSsbxNrRFi9wrf+M7Q== example@example.com" ', $this->command->getUsages()[0]);
  }

  /**
   * @param $name
   *
   * @return \Acquia\Cli\Command\Api\ApiCommandBase|null
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getApiCommandByName($name): ?ApiCommandBase {
    $api_command_helper = new ApiCommandHelper();
    $api_commands = $api_command_helper->getApiCommands();
    foreach ($api_commands as $api_command) {
      if ($api_command->getName() === $name) {
        return $api_command;
      }
    }

    return NULL;
  }

}
