<?php

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * @property \Acquia\Cli\Command\Api\ApiBaseCommand $command
 */
class ApiCommandTest extends CommandTestBase {

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->clientProphecy->addOption('headers', ['Accept' => 'application/json']);
    putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');
  }

  protected function createCommand(): Command {
    return $this->injectCommand(ApiBaseCommand::class);
  }

  public function testArgumentsInteraction(): void {
    $this->command = $this->getApiCommandByName('api:environments:log-download');
    $this->executeCommand([], [
      '289576-53785bca-1946-4adc-a022-e50d24686c20',
      'apache-access',
    ]);
    $output = $this->getDisplay();
    $this->assertStringContainsString('Enter a value for environmentId', $output);
    $this->assertStringContainsString('logType is a required argument', $output);
    $this->assertStringContainsString('An ID that uniquely identifies a log type.', $output);
    $this->assertStringContainsString('apache-access', $output);
    $this->assertStringContainsString('Select a value for logType', $output);
  }

  public function testArgumentsInteractionValidation(): void {
    $this->command = $this->getApiCommandByName('api:environments:variable-update');
    try {
      $this->executeCommand([], [
        '289576-53785bca-1946-4adc-a022-e50d24686c20',
        'AH_SOMETHING',
        'AH_SOMETHING',
      ]);
    }
    catch (MissingInputException $exception) {

    }
    $output = $this->getDisplay();
    $this->assertStringContainsString('It must match the pattern', $output);
  }

  public function testArgumentsInteractionValdationFormat(): void {
    $this->command = $this->getApiCommandByName('api:notifications:find');
    try {
      $this->executeCommand([], [
        'test',
      ]);
    }
    catch (MissingInputException $exception) {

    }
    $output = $this->getDisplay();
    $this->assertStringContainsString('This is not a valid UUID', $output);
  }

  /**
   * Tests invalid UUID.
   */
  public function testApiCommandErrorResponse(): void {
    $invalidUuid = '257a5440-22c3-49d1-894d-29497a1cf3b9';
    $this->command = $this->getApiCommandByName('api:applications:find');
    $mockBody = $this->getMockResponseFromSpec($this->command->getPath(), $this->command->getMethod(), '404');
    $this->clientProphecy->request('get', '/applications/' . $invalidUuid)->willThrow(new ApiErrorException($mockBody))->shouldBeCalled();

    // ApiCommandBase::convertApplicationAliastoUuid() will try to convert the invalid string to a uuid:
    $this->clientProphecy->addQuery('filter', 'hosting=@*:' . $invalidUuid);
    $this->clientProphecy->request('get', '/applications')->willReturn([]);

    $this->executeCommand(['applicationUuid' => $invalidUuid], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      '0',
      // Would you like to link the Cloud application Sample application to this repository?
      'n',
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertJson($output);
    $this->assertStringContainsString($mockBody->message, $output);
    $this->assertEquals(1, $this->getStatusCode());
  }

  public function testApiCommandExecutionForHttpGet(): void {
    $mockBody = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $this->clientProphecy->addQuery('limit', '1')->shouldBeCalled();
    $this->clientProphecy->request('get', '/account/ssh-keys')->willReturn($mockBody->{'_embedded'}->items)->shouldBeCalled();
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

  public function testInferApplicationUuidArgument(): void {
    $mockBody = $this->getMockResponseFromSpec('/applications/{applicationUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/applications')->willReturn([$mockBody])->shouldBeCalled();
    $this->clientProphecy->request('get', '/applications/' . $mockBody->uuid)->willReturn($mockBody)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:applications:find');
    $this->executeCommand([], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      '0',
      // Would you like to link the Cloud application Sample application to this repository?
      'n',
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Inferring Cloud Application UUID for this command since none was provided...', $output);
    $this->assertStringContainsString('Set application uuid to ' . $mockBody->uuid, $output);
    $this->assertEquals(0, $this->getStatusCode());
  }

  public function providerTestConvertApplicationAliasToUuidArgument() {
    return [
      [FALSE],
      [TRUE],
    ];
  }

  /**
   * @dataProvider providerTestConvertApplicationAliasToUuidArgument
   * @group serial
   */
  public function testConvertApplicationAliasToUuidArgument(bool $support): void {
    ClearCacheCommand::clearCaches();
    $tamper = function (&$response): void {
      unset($response[1]);
    };
    $applications = $this->mockRequest('getApplications', NULL, NULL, NULL, $tamper);
    $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
    $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:applications:find');
    $alias = 'devcloud2';
    $tamper = NULL;
    if ($support) {
      $this->clientProphecy->addQuery('all', 'true')->shouldBeCalled();
      $tamper = function ($response): void {
        $response->flags->support = TRUE;
      };
    }
    $this->mockRequest('getAccount', NULL, NULL, NULL, $tamper);

    $this->executeCommand(['applicationUuid' => $alias], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      self::$INPUT_DEFAULT_CHOICE,
      // Would you like to link the Cloud application Sample application to this repository?
      'n',
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
  }

  public function testConvertInvalidApplicationAliasToUuidArgument(): void {
    $this->mockApplicationsRequest(0);
    $this->clientProphecy->addQuery('filter', 'hosting=@*:invalidalias')->shouldBeCalled();
    $this->mockRequest('getAccount');
    $this->command = $this->getApiCommandByName('api:applications:find');
    $alias = 'invalidalias';
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('No applications match the alias *:invalidalias');
    $this->executeCommand(['applicationUuid' => $alias], []);
    $this->prophet->checkPredictions();
  }

  /**
   * @serial
   */
  public function testConvertNonUniqueApplicationAliasToUuidArgument(): void {
    ClearCacheCommand::clearCaches();
    $this->mockApplicationsRequest(2, FALSE);
    $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')->shouldBeCalled();
    $this->mockRequest('getAccount');
    $this->command = $this->getApiCommandByName('api:applications:find');
    $alias = 'devcloud2';
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Multiple applications match the alias *:devcloud2');
    $this->executeCommand(['applicationUuid' => $alias], []);
    $output = $this->getDisplay();
    $this->assertStringContainsString('Use a unique application alias: devcloud:devcloud2, devcloud:devcloud2', $output);

    $this->prophet->checkPredictions();
  }

  public function testConvertApplicationAliasWithRealmToUuidArgument(): void {
    $this->mockApplicationsRequest(1, FALSE);
    $this->clientProphecy->addQuery('filter', 'hosting=@devcloud:devcloud2')->shouldBeCalled();
    $this->mockApplicationRequest();
    $this->mockRequest('getAccount');
    $this->command = $this->getApiCommandByName('api:applications:find');
    $alias = 'devcloud:devcloud2';
    $this->executeCommand(['applicationUuid' => $alias], []);
    $this->prophet->checkPredictions();
  }

  /**
   * @serial
   */
  public function testConvertEnvironmentAliasToUuidArgument(): void {
    ClearCacheCommand::clearCaches();
    $applicationsResponse = $this->mockApplicationsRequest(1);
    $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')->shouldBeCalled();
    $this->mockEnvironmentsRequest($applicationsResponse);
    $this->mockRequest('getAccount');

    $response = $this->getMockEnvironmentResponse();
    $this->clientProphecy->request('get', '/environments/24-a47ac10b-58cc-4372-a567-0e02b2c3d470')->willReturn($response)->shouldBeCalled();

    $this->command = $this->getApiCommandByName('api:environments:find');
    $alias = 'devcloud2.dev';

    $this->executeCommand(['environmentId' => $alias], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      '0',
      // Would you like to link the Cloud application Sample application to this repository?
      'n',
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
  }

  /**
   * @group serial
   */
  public function testConvertInvalidEnvironmentAliasToUuidArgument(): void {
    ClearCacheCommand::clearCaches();
    $applicationsResponse = $this->mockApplicationsRequest(1);
    $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')->shouldBeCalled();
    $this->mockEnvironmentsRequest($applicationsResponse);
    $this->mockRequest('getAccount');
    $this->command = $this->getApiCommandByName('api:environments:find');
    $alias = 'devcloud2.invalid';
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('{environmentId} must be a valid UUID or site alias.');
    $this->executeCommand(['environmentId' => $alias], []);
    $this->prophet->checkPredictions();
  }

  public function testApiCommandExecutionForHttpPost(): void {
    $mockRequestArgs = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $mockResponseBody = $this->getMockResponseFromSpec('/account/ssh-keys', 'post', '202');
    foreach ($mockRequestArgs as $name => $value) {
      $this->clientProphecy->addOption('json', [$name => $value])->shouldBeCalled();
    }
    $this->clientProphecy->request('post', '/account/ssh-keys')->willReturn($mockResponseBody)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:accounts:ssh-key-create');
    $this->executeCommand($mockRequestArgs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertNotNull($output);
    $this->assertJson($output);
    $this->assertStringContainsString('Adding SSH key.', $output);
  }

  public function testApiCommandExecutionForHttpPut(): void {
    $mockRequestOptions = $this->getMockRequestBodyFromSpec('/environments/{environmentId}', 'put');
    $mockRequestOptions['max_input_vars'] = 1001;
    $mockResponseBody = $this->getMockEnvironmentResponse('put', '202');

    foreach ($mockRequestOptions as $name => $value) {
      $this->clientProphecy->addOption('json', [$name => $value])->shouldBeCalled();
    }
    $this->clientProphecy->request('put', '/environments/24-a47ac10b-58cc-4372-a567-0e02b2c3d470')->willReturn($mockResponseBody)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:environments:update');

    $options = [];
    foreach ($mockRequestOptions as $key => $value) {
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

  public function providerTestApiCommandDefinitionParameters(): array {
    $apiAccountsSshKeysListUsage = '--from="-7d" --to="-1d" --sort="field1,-field2" --limit="10" --offset="10"';
    return [
      ['0', 'api:accounts:ssh-keys-list', 'get', $apiAccountsSshKeysListUsage],
      ['1', 'api:accounts:ssh-keys-list', 'get', $apiAccountsSshKeysListUsage],
      ['1', 'api:accounts:ssh-keys-list', 'get', $apiAccountsSshKeysListUsage],
      ['1', 'api:environments:domain-clear-caches', 'post', '12-d314739e-296f-11e9-b210-d663bd873d93 example.com'],
      ['1', 'api:applications:find', 'get', 'da1c0a8e-ff69-45db-88fc-acd6d2affbb7'],
      ['1', 'api:applications:find', 'get', 'myapp'],
    ];
  }

  /**
   * @dataProvider providerTestApiCommandDefinitionParameters
   * @param $useSpecCache
   * @param $commandName
   * @param $method
   * @param $usage
   */
  public function testApiCommandDefinitionParameters($useSpecCache, $commandName, $method, $usage): void {
    putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=' . $useSpecCache);

    $this->command = $this->getApiCommandByName($commandName);
    $resource = $this->getResourceFromSpec($this->command->getPath(), $method);
    $this->assertEquals($resource['summary'], $this->command->getDescription());

    $expectedCommandName = 'api:' . $resource['x-cli-name'];
    $this->assertEquals($expectedCommandName, $this->command->getName());

    foreach ($resource['parameters'] as $parameter) {
      $paramKey = str_replace('#/components/parameters/', '', $parameter['$ref']);
      $param = $this->getCloudApiSpec()['components']['parameters'][$paramKey];
      $this->assertTrue(
            $this->command->getDefinition()->hasOption($param['name']) ||
            $this->command->getDefinition()->hasArgument($param['name']),
            "Command $expectedCommandName does not have expected argument or option {$param['name']}"
        );
    }

    $usages = $this->command->getUsages();
    $this->assertContains($commandName . ' ' . $usage, $usages);
  }

  public function testModifiedParameterDescriptions(): void {
    $this->command = $this->getApiCommandByName('api:environments:domain-status-find');
    $this->assertStringContainsString('You may also use an environment alias', $this->command->getDefinition()->getArgument('environmentId')->getDescription());

    $this->command = $this->getApiCommandByName('api:applications:find');
    $this->assertStringContainsString('You may also use an application alias or omit the argument', $this->command->getDefinition()->getArgument('applicationUuid')->getDescription());
  }

  public function providerTestApiCommandDefinitionRequestBody(): array {
    return [
      ['api:accounts:ssh-key-create', 'post', 'api:accounts:ssh-key-create "mykey" "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQChwPHzTTDKDpSbpa2+d22LcbQmsw92eLsUK3Fmei1fiGDkd34NsYCN8m7lsi3NbvdMS83CtPQPWiCveYPzFs1/hHc4PYj8opD2CNnr5iWVVbyaulCYHCgVv4aB/ojcexg8q483A4xJeF15TiCr/gu34rK6ucTvC/tn/rCwJBudczvEwt0klqYwv8Cl/ytaQboSuem5KgSjO3lMrb6CWtfSNhE43ZOw+UBFBqxIninN868vGMkIv9VY34Pwj54rPn/ItQd6Ef4B0KHHaGmzK0vfP+AK7FxNMoHnj3iYT33KZNqtDozdn5tYyH/bThPebEtgqUn+/w5l6wZIC/8zzvls/127ngHk+jNa0PlNyS2TxhPUK4NaPHIEnnrlp07JEYC4ImcBjaYCWAdcTcUkcJjwZQkN4bGmyO9cjICH98SdLD/HxqzTHeaYDbAX/Hu9HfaBb5dXLWsjw3Xc6hoVnUUZbMQyfgb0KgxDLh92eNGxJkpZiL0VDNOWCxDWsNpzwhLNkLqCvI6lyxiLaUzvJAk6dPaRhExmCbU1lDO2eR0FdSwC1TEhJOT9eDIK1r2hztZKs2oa5FNFfB/IFHVWasVFC9N2h/r/egB5zsRxC9MqBLRBq95NBxaRSFng6ML5WZSw41Qi4C/JWVm89rdj2WqScDHYyAdwyyppWU4T5c9Fmw== example@example.com"'],
      ['api:environments:file-copy', 'post', '12-d314739e-296f-11e9-b210-d663bd873d93 --source="14-0c7e79ab-1c4a-424e-8446-76ae8be7e851"'],
    ];
  }

  /**
   * @dataProvider providerTestApiCommandDefinitionRequestBody
   * @param $commandName
   * @param $method
   * @param $usage
   */
  public function testApiCommandDefinitionRequestBody($commandName, $method, $usage): void {
    $this->command = $this->getApiCommandByName($commandName);
    $resource = $this->getResourceFromSpec($this->command->getPath(), $method);
    foreach ($resource['requestBody']['content']['application/json']['example'] as $propKey => $value) {
      $this->assertTrue($this->command->getDefinition()->hasArgument($propKey) || $this->command->getDefinition()
          ->hasOption($propKey),
        "Command {$this->command->getName()} does not have expected argument or option $propKey");
    }
    $this->assertStringContainsString($usage, $this->command->getUsages()[0]);
  }

  public function testGetApplicationUuidFromBltYml(): void {
    $mockBody = $this->getMockResponseFromSpec('/applications/{applicationUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/applications/' . $mockBody->uuid)->willReturn($mockBody)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('api:applications:find');
    $bltConfigFilePath = Path::join($this->projectDir, 'blt', 'blt.yml');
    $this->fs->dumpFile($bltConfigFilePath, Yaml::dump(['cloud' => ['appId' => $mockBody->uuid]]));
    $this->executeCommand();
    $this->prophet->checkPredictions();
    $this->getDisplay();
    $this->fs->remove($bltConfigFilePath);
  }

  /**
   * Test of deletion of the user from organization by user uuid.
   */
  public function testOrganizationMemberDeleteByUserUuid(): void {
    $orgId = 'bfafd31a-83a6-4257-b0ec-afdeff83117a';
    $memberUuid = '26c4af83-545b-45cb-b165-d537adc9e0b4';

    $this->mockRequest('postOrganizationMemberDelete', [$orgId, $memberUuid], NULL, 'Member removed');

    $this->command = $this->getApiCommandByName('api:organizations:member-delete');
    $this->executeCommand(
      [
        'organizationUuid' => $orgId,
        'userUuid' => $memberUuid,
      ],
    );

    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("Organization member removed", $output);
  }

  /**
   * Test of deletion of the user from organization by user email.
   */
  public function testOrganizationMemberDeleteByUserEmail(): void {
    $membersResponse = $this->getMockResponseFromSpec('/organizations/{organizationUuid}/members', 'get', 200);
    $orgId = 'bfafd31a-83a6-4257-b0ec-afdeff83117a';
    $memberUuid = $membersResponse->_embedded->items[0]->mail;
    $this->clientProphecy->request('get', '/organizations/' . $orgId . '/members')
      ->willReturn($membersResponse->_embedded->items)->shouldBeCalled();

    $response = $this->getMockResponseFromSpec('/organizations/{organizationUuid}/members/{userUuid}', 'delete', 200);
    $this->clientProphecy->request('delete', '/organizations/' . $orgId . '/members/' . $membersResponse->_embedded->items[0]->uuid)
      ->willReturn($response->{'Member removed'}->value)->shouldBeCalled();

    $this->command = $this->getApiCommandByName('api:organizations:member-delete');
    $this->executeCommand(
      [
        'organizationUuid' => $orgId,
        'userUuid' => $memberUuid,
      ],
    );

    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("Organization member removed", $output);
  }

}
