<?php

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiCommandBase;
use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
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
   * Tests invalid UUID.
   */
  public function testApiCommandErrorResponse(): void {
    $invalid_uuid = '257a5440-22c3-49d1-894d-29497a1cf3b9';
    $this->command = $this->getApiCommandByName('api:applications:find');
    $mock_body = $this->getMockResponseFromSpec($this->command->getPath(), $this->command->getMethod(), '404');
    $this->clientProphecy->request('get', '/applications/' . $invalid_uuid)->willThrow(new ApiErrorException($mock_body))->shouldBeCalled();

    // ApiCommandBase::convertApplicationAliastoUuid() will try to convert the invalid string to a uuid:
    $this->clientProphecy->addQuery('filter', 'hosting=@*' . $invalid_uuid);
    $this->clientProphecy->request('get', '/applications')->willReturn([]);

    $this->executeCommand(['applicationUuid' => $invalid_uuid], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      '0',
      // Would you like to link the Cloud application Sample application to this repository?
      'n'
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertJson($output);
    $this->assertStringContainsString($mock_body->message, $output);
    $this->assertEquals(1, $this->getStatusCode());
  }

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
      // Please select a Cloud Platform application:
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

  public function testConvertApplicationAliasToUuidArgument(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->clientProphecy->addQuery('filter', 'hosting=@*devcloud2')->shouldBeCalled();
    $this->mockApplicationRequest();
    $this->command = $this->getApiCommandByName('api:applications:find');
    $alias = 'devcloud2';
    $this->clientProphecy->clearQuery()->shouldBeCalled();

    $this->executeCommand(['applicationUuid' => $alias], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      '0',
      // Would you like to link the Cloud application Sample application to this repository?
      'n'
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
  }

  public function testConvertInvalidApplicationAliasToUuidArgument(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->clientProphecy->addQuery('filter', 'hosting=@*invalidalias')->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:applications:find');
    $alias = 'invalidalias';
    try {
      $this->executeCommand(['applicationUuid' => $alias], []);
    }
    catch (AcquiaCliException $exception) {
      $this->assertEquals('Application not found matching the alias ' . $alias, $exception->getMessage());
    }
    $this->prophet->checkPredictions();
  }

  public function testConvertEnvironmentAliasToUuidArgument(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->clientProphecy->addQuery('filter', 'hosting=@*devcloud2')->shouldBeCalled();
    $this->clientProphecy->clearQuery()->shouldBeCalled();
    $this->mockEnvironmentsRequest($applications_response);

    $response = $this->getMockEnvironmentResponse();
    $this->clientProphecy->request('get', '/environments/24-a47ac10b-58cc-4372-a567-0e02b2c3d470')->willReturn($response)->shouldBeCalled();

    $this->command = $this->getApiCommandByName('api:environments:find');
    $alias = 'devcloud2.dev';

    $this->executeCommand(['environmentId' => $alias], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      '0',
      // Would you like to link the Cloud application Sample application to this repository?
      'n'
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
  }

  public function testConvertInvalidEnvironmentAliasToUuidArgument(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->clientProphecy->addQuery('filter', 'hosting=@*devcloud2')->shouldBeCalled();
    $this->clientProphecy->clearQuery()->shouldBeCalled();
    $this->mockEnvironmentsRequest($applications_response);
    $this->command = $this->getApiCommandByName('api:environments:find');
    $alias = 'devcloud2.invalid';
    try {
      $this->executeCommand(['environmentId' => $alias], []);
    }
    catch (AcquiaCliException $exception) {
      $this->assertEquals('The {environmentId} must be a valid UUID or site alias.', $exception->getMessage());
    }
    $this->prophet->checkPredictions();
  }

  public function testApiCommandExecutionForHttpPost(): void {
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $mock_response_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'post', '202');
    foreach ($mock_request_args as $name => $value) {
      $this->clientProphecy->addOption('json', [$name => $value])->shouldBeCalled();
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

  public function testApiCommandExecutionForHttpPut(): void {
    $mock_request_options = $this->getMockRequestBodyFromSpec('/environments/{environmentId}', 'put');
    $mock_request_options['max_input_vars'] = 1001;
    $mock_response_body = $this->getMockEnvironmentResponse('put', '202');

    foreach ($mock_request_options as $name => $value) {
      $this->clientProphecy->addOption('json', [$name => $value])->shouldBeCalled();
    }
    $this->clientProphecy->request('put', '/environments/24-a47ac10b-58cc-4372-a567-0e02b2c3d470')->willReturn($mock_response_body)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:environments:update');

    $options = [];
    foreach ($mock_request_options as $key => $value) {
      $options['--' . $key] = $value;
    }
    $options['--lang_version'] = $options['--version'];
    unset($options['--version']);
    $args = ['environmentId' => '24-a47ac10b-58cc-4372-a567-0e02b2c3d470'] + $options;
    $this->executeCommand($args);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertNotNull($output);
    $this->assertJson($output);
    $this->assertStringContainsString('The environment configuration is being updated.', $output);
  }

  /**
   *
   */
  public function providerTestApiCommandDefinitionParameters(): array {
    $api_accounts_ssh_keys_list_usage = '--from="-7d" --to="-1d" --sort="field1,-field2" --limit="10" --offset="10"';
    return [
      ['0', 'api:accounts:ssh-keys-list', 'get', $api_accounts_ssh_keys_list_usage],
      ['1', 'api:accounts:ssh-keys-list', 'get', $api_accounts_ssh_keys_list_usage],
      ['1', 'api:accounts:ssh-keys-list', 'get', $api_accounts_ssh_keys_list_usage],
      ['1', 'api:environments:domains-clear-varnish', 'post', '12-d314739e-296f-11e9-b210-d663bd873d93 --domains="domain1.example.com" --domains="domain2.example.com"'],
      ['1', 'api:environments:domains-clear-varnish', 'post', 'myapp.dev --domains="domain1.example.com" --domains="domain2.example.com"'],
      ['1', 'api:applications:find', 'get', 'da1c0a8e-ff69-45db-88fc-acd6d2affbb7'],
      ['1', 'api:applications:find', 'get', 'myapp'],
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

    $usages = $this->command->getUsages();
    $this->assertContains($command_name . ' ' . $usage, $usages);
  }

  public function testModifiedParameterDescriptions(): void {
    $this->command = $this->getApiCommandByName('api:environments:domains-clear-varnish');
    $this->assertStringContainsString('You may also use an environment alias', $this->command->getDefinition()->getArgument('environmentId')->getDescription());

    $this->command = $this->getApiCommandByName('api:applications:find');
    $this->assertStringContainsString('You may also use an application alias or omit the argument', $this->command->getDefinition()->getArgument('applicationUuid')->getDescription());
  }

  public function providerTestApiCommandDefinitionRequestBody(): array {
    return [
      ['api:accounts:ssh-key-create', 'post', 'api:accounts:ssh-key-create "mykey" "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQChwPHzTTDKDpSbpa2+d22LcbQmsw92eLsUK3Fmei1fiGDkd34NsYCN8m7lsi3NbvdMS83CtPQPWiCveYPzFs1/hHc4PYj8opD2CNnr5iWVVbyaulCYHCgVv4aB/ojcexg8q483A4xJeF15TiCr/gu34rK6ucTvC/tn/rCwJBudczvEwt0klqYwv8Cl/ytaQboSuem5KgSjO3lMrb6CWtfSNhE43ZOw+UBFBqxIninN868vGMkIv9VY34Pwj54rPn/ItQd6Ef4B0KHHaGmzK0vfP+AK7FxNMoHnj3iYT33KZNqtDozdn5tYyH/bThPebEtgqUn+/w5l6wZIC/8zzvls/127ngHk+jNa0PlNyS2TxhPUK4NaPHIEnnrlp07JEYC4ImcBjaYCWAdcTcUkcJjwZQkN4bGmyO9cjICH98SdLD/HxqzTHeaYDbAX/Hu9HfaBb5dXLWsjw3Xc6hoVnUUZbMQyfgb0KgxDLh92eNGxJkpZiL0VDNOWCxDWsNpzwhLNkLqCvI6lyxiLaUzvJAk6dPaRhExmCbU1lDO2eR0FdSwC1TEhJOT9eDIK1r2hztZKs2oa5FNFfB/IFHVWasVFC9N2h/r/egB5zsRxC9MqBLRBq95NBxaRSFng6ML5WZSw41Qi4C/JWVm89rdj2WqScDHYyAdwyyppWU4T5c9Fmw== example@example.com"'],
      ['api:environments:domains-clear-varnish', 'postestApiCommandErrorResponset', 'api:environments:domains-clear-varnish 12-d314739e-296f-11e9-b210-d663bd873d93 --domains="domain1.example.com" --domains="domain2.example.com"'],
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
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->telemetryHelper,
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
