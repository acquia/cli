<?php

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiCommandBase;
use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

/**
 * Class ApiCommandTest.
 *
 * @property ApiCommandBase $command
 * @package Acquia\Cli\Tests\Api
 */
class ApiCommandTest extends CommandTestBase {

  public function setUp($output = NULL): void {
    parent::setUp($output);
    putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ApiCommandBase::class);
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

  public function testInferApplicationUuidArgument() {
    $mock_body = $this->getMockResponseFromSpec('/applications/{applicationUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/applications')->willReturn([$mock_body])->shouldBeCalled();
    $this->clientProphecy->request('get', '/applications/' . $mock_body->uuid)->willReturn($mock_body)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:applications:find');
    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select an Acquia Cloud application:
      '0',
      // Would you like to link the Cloud application Sample application to this repository?
      'n'
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Inferring Cloud Application UUID for this command since none was provided...', $output);
    $this->assertStringContainsString('Set application uuid to ' . $mock_body->uuid, $output);
    $this->assertEquals(0, $this->getStatusCode());
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
   *
   */
  public function providerTestApiCommandDefinitionParameters(): array {
    $api_accounts_ssh_keys_list_usage = 'api:accounts:ssh-keys-list --from="-7d" --to="-1d" --sort="field1,-field2" --limit="10" --offset="10" ';
    return [
      ['0', 'api:accounts:ssh-keys-list', 'get', $api_accounts_ssh_keys_list_usage],
      ['1', 'api:accounts:ssh-keys-list', 'get', $api_accounts_ssh_keys_list_usage],
      ['1', 'api:accounts:ssh-keys-list', 'get', $api_accounts_ssh_keys_list_usage],
      ['1', 'api:environments:domains-clear-varnish', 'post', '12-d314739e-296f-11e9-b210-d663bd873d93 --domains="domain1.example.com" --domains="domain2.example.com"'],
    ];
  }

  /**
   * @dataProvider providerTestApiCommandDefinitionParameters
   *
   * @param $use_spec_cache
   * @param $command_name
   * @param $method
   * @param $usage
   *
   * @throws \Psr\Cache\InvalidArgumentException
   * @group noCache
   */
  public function testApiCommandDefinitionParameters($use_spec_cache, $command_name, $method, $usage): void {
    putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=' . $use_spec_cache);

    $this->command = $this->getApiCommandByName($command_name);
    $resource = $this->getResourceFromSpec($this->command->getPath(), $method);
    $this->assertEquals($resource['summary'], $this->command->getDescription());

    $expected_command_name = 'api:' . $resource['x-cli-name'];
    $this->assertEquals($expected_command_name, $this->command->getName());

    foreach ($resource['parameters'] as $parameter) {
      $param_key = str_replace('#/components/parameters/', '', $parameter['$ref']);
      $param = $this->getCloudApiSpec()['components']['parameters'][$param_key];
      $this->assertTrue(
            $this->command->getDefinition()->hasOption($param['name']) ||
            $this->command->getDefinition()->hasArgument($param['name']),
            "Command $expected_command_name does not have expected argument or option {$param['name']}"
        );
    }
    $this->assertStringContainsString($usage, $this->command->getUsages()[0]);
  }

  public function providerTestApiCommandDefinitionRequestBody(): array {
    return [
      ['api:accounts:ssh-key-create', 'post', 'api:accounts:ssh-key-create "mykey" "ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEAklOUpkDHrfHY17SbrmTIpNLTGK9Tjom/BWDSUGPl+nafzlHDTYW7hdI4yZ5ew18JH4JW9jbhUFrviQzM7xlELEVf4h9lFX5QVkbPppSwg0cda3Pbv7kOdJ/MTyBlWXFCR+HAo3FXRitBqxiX1nKhXpHAZsMciLq8V6RjsNAQwdsdMFvSlVK/7XAt3FaoJoAsncM1Q9x5+3V0Ww68/eIFmb1zuUFljQJKprrX88XypNDvjYNby6vw/Pb0rwert/EnmZ+AW4OZPnTPI89ZPmVMLuayrD2cE86Z/il8b+gw3r3+1nKatmIkjn2so1d01QraTlMqVSsbxNrRFi9wrf+M7Q== example@example.com" '],
      ['api:environments:domains-clear-varnish', 'post', 'api:environments:domains-clear-varnish 12-d314739e-296f-11e9-b210-d663bd873d93 --domains="domain1.example.com" --domains="domain2.example.com" '],
    ];
  }

  /**
   * @dataProvider providerTestApiCommandDefinitionRequestBody
   *
   * @param $command_name
   * @param $method
   * @param $usage
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testApiCommandDefinitionRequestBody($command_name, $method, $usage): void {
    $this->command = $this->getApiCommandByName($command_name);
    $resource = $this->getResourceFromSpec($this->command->getPath(), $method);
    foreach ($resource['requestBody']['content']['application/json']['example'] as $prop_key => $value) {
      $this->assertTrue($this->command->getDefinition()->hasArgument($prop_key) || $this->command->getDefinition()
          ->hasOption($prop_key),
        "Command {$this->command->getName()} does not have expected argument or option $prop_key");
    }
    $this->assertStringContainsString($usage, $this->command->getUsages()[0]);
  }

  public function testGetApplicationUuidFromBltYml(): void {
    $mock_body = $this->getMockResponseFromSpec('/applications/{applicationUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/applications/' . $mock_body->uuid)->willReturn($mock_body)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:applications:find');
    $blt_config_file_path = Path::join($this->projectFixtureDir, 'blt', 'blt.yml');
    $this->fs->dumpFile($blt_config_file_path, Yaml::dump(['cloud' => ['appId' => $mock_body->uuid]]));
    $this->executeCommand();
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->fs->remove($blt_config_file_path);
  }

  /**
   * @param $name
   *
   * @return \Acquia\Cli\Command\Api\ApiCommandBase|null
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getApiCommandByName($name): ?ApiCommandBase {
    $api_command_helper = new ApiCommandHelper(
      $this->cloudConfigFilepath,
      $this->localMachineHelper,
      $this->cloudDatastore,
      $this->acliDatastore,
      $this->telemetryHelper,
      $this->amplitudeProphecy->reveal(),
      $this->acliConfigFilename,
      $this->projectFixtureDir,
      $this->clientServiceProphecy->reveal(),
      $this->logStreamManagerProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir
    );
    $api_commands = $api_command_helper->getApiCommands();
    foreach ($api_commands as $api_command) {
      if ($api_command->getName() === $name) {
        return $api_command;
      }
    }

    return NULL;
  }

}
