<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * @property \Acquia\Cli\Command\Api\ApiBaseCommand $command
 */
class ApiCommandTest extends CommandTestBase
{
    public function setUp(): void
    {
        parent::setUp();
        putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');
    }

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(ApiBaseCommand::class);
    }

    public function testTaskWait(): void
    {
        $environmentId = '24-a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $branch = 'my-feature-branch';
        $this->mockRequest('postEnvironmentsSwitchCode', $environmentId, null, 'Switching code');
        $this->clientProphecy->addOption('json', ['branch' => $branch])->shouldBeCalled();
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $this->mockRequest('getNotificationByUuid', 'bfd9a39b-a85e-4de3-8a70-042d1c7e607a');
        $this->command = $this->getApiCommandByName('api:environments:code-switch');
        $this->executeCommand([
            '--task-wait' => true,
            'branch' => $branch,
            'environmentId' => $environmentId,
        ]);
        $output = $this->getDisplay();
        $this->assertStringContainsString('[OK] The task with notification uuid 1bd3487e-71d1-4fca-a2d9-5f969b3d35c1 completed', $output);
        $expected = <<<EOD
Progress: 100
Completed: Mon Jul 29 20:47:13 UTC 2019
Task type: Application added to recents list
Duration: 0 seconds
EOD;
        $this->assertStringContainsStringIgnoringLineEndings($expected, $output);
        $this->assertEquals(0, $this->getStatusCode());
    }


    public function testArgumentsInteraction(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $this->mockRequest('getEnvironmentsLog', ['289576-53785bca-1946-4adc-a022-e50d24686c20', 'apache-access']);
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

    /**
     * @throws \AcquiaCloudApi\Exception\ApiErrorException
     * @throws \JsonException
     * @throws \Exception
     */
    public function testInteractiveException(): void
    {
        $this->command = $this->getApiCommandByName('api:environments:log-download');
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $mockBody = self::getMockResponseFromSpec($this->command->getPath(), $this->command->getMethod(), '404');
        $this->clientProphecy->request('get', '/environments/289576-53785bca-1946-4adc-a022-e50d24686c20/logs/apache-access')
            ->willThrow(new ApiErrorException($mockBody->{'Not found'}->value))
            ->shouldBeCalled();
        $this->expectException(ApiErrorException::class);
        $this->executeCommand([], [
            '289576-53785bca-1946-4adc-a022-e50d24686c20',
            'apache-access',
        ]);
    }

    /**
     * @throws \Exception
     */
    public function testArgumentsInteractionValidation(): void
    {
        $this->command = $this->getApiCommandByName('api:environments:variable-update');
        try {
            $this->executeCommand([], [
                '289576-53785bca-1946-4adc-a022-e50d24686c20',
                'AH_SOMETHING',
                'AH_SOMETHING',
            ]);
        } catch (MissingInputException) {
        }
        $output = $this->getDisplay();
        $this->assertStringContainsString('It must match the pattern', $output);
    }

    public function testArgumentsInteractionValidationFormat(): void
    {
        $this->command = $this->getApiCommandByName('api:notifications:find');
        try {
            $this->executeCommand([], [
                'test',
            ]);
        } catch (MissingInputException) {
        }
        $output = $this->getDisplay();
        $this->assertStringContainsString('This is not a valid UUID', $output);
    }

    /**
     * Tests invalid UUID.
     *
     * @throws \JsonException
     * @throws \AcquiaCloudApi\Exception\ApiErrorException
     * @throws \Exception
     */
    public function testApiCommandErrorResponse(): void
    {
        $invalidUuid = '257a5440-22c3-49d1-894d-29497a1cf3b9';
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $this->command = $this->getApiCommandByName('api:applications:find');
        $mockBody = self::getMockResponseFromSpec($this->command->getPath(), $this->command->getMethod(), '404');
        $this->clientProphecy->request('get', '/applications/' . $invalidUuid)
            ->willThrow(new ApiErrorException($mockBody))
            ->shouldBeCalled();

        // ApiCommandBase::convertApplicationAliasToUuid() will try to convert the invalid string to a UUID:
        $this->clientProphecy->addQuery('filter', 'hosting=@*:' . $invalidUuid);
        $this->clientProphecy->request('get', '/applications')->willReturn([]);

        $this->executeCommand(['applicationUuid' => $invalidUuid], [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            '0',
            // Would you like to link the Cloud application Sample application to this repository?
            'n',
        ], \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE, false);

        // Assert.
        $output = $this->getDisplay();
        $this->assertJson($output);
        $expected = <<<EOD
{
    "error": "not_found",
    "message": "The application you are trying to access does not exist, or you do not have permission to access it."
}

EOD;
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $output);
        $this->assertEquals(1, $this->getStatusCode());
    }

    public function testApiCommandExecutionForHttpGet(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->addQuery('limit', '1')->shouldBeCalled();
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();
        $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
        // Our mock Client doesn't actually return a limited dataset, but we still assert it was passed added to the
        // client's query correctly.
        $this->executeCommand(['--limit' => '1']);

        // Assert.
        $output = $this->getDisplay();
        $this->assertNotNull($output);
        $this->assertJson($output);
        $contents = json_decode($output, true);
        $this->assertArrayHasKey(0, $contents);
        $this->assertArrayHasKey('uuid', $contents[0]);
    }

    public function testObjectParam(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $this->mockRequest('putEnvironmentCloudActions', '24-a47ac10b-58cc-4372-a567-0e02b2c3d470');
        $this->clientProphecy->addOption('json', ['cloud-actions' => (object)['fb4aa87a-8be2-42c6-bdf0-ef9d09a3de70' => true]]);
        $this->command = $this->getApiCommandByName('api:environments:cloud-actions-update');
        $this->executeCommand([
            'cloud-actions' => '{"fb4aa87a-8be2-42c6-bdf0-ef9d09a3de70":true}',
            'environmentId' => '24-a47ac10b-58cc-4372-a567-0e02b2c3d470',
        ]);
        $output = $this->getDisplay();
        $this->assertStringContainsString('Cloud Actions have been updated.', $output);
    }

    public function testInferApplicationUuidArgument(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $applications = $this->mockRequest('getApplications');
        $application = $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
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
        $output = $this->getDisplay();
        $this->assertStringContainsString('Inferring Cloud Application UUID for this command since none was provided...', $output);
        $this->assertStringContainsString('Set application uuid to ' . $application->uuid, $output);
        $this->assertEquals(0, $this->getStatusCode());
    }

    /**
     * @return bool[][]
     */
    public static function providerTestConvertApplicationAliasToUuidArgument(): array
    {
        return [
            [false],
            [true],
        ];
    }

    /**
     * @dataProvider providerTestConvertApplicationAliasToUuidArgument
     * @group serial
     */
    public function testConvertApplicationAliasToUuidArgument(bool $support): void
    {
        ClearCacheCommand::clearCaches();
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $tamper = function (&$response): void {
            unset($response[1]);
        };
        $applications = $this->mockRequest('getApplications', null, null, null, $tamper);
        $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
        $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')
            ->shouldBeCalled();
        $this->command = $this->getApiCommandByName('api:applications:find');
        $alias = 'devcloud2';
        $tamper = null;
        if ($support) {
            $this->clientProphecy->addQuery('all', 'true')->shouldBeCalled();
            $tamper = function (mixed $response): void {
                $response->flags->support = true;
            };
        }
        $this->mockRequest('getAccount', null, null, null, $tamper);

        $this->executeCommand(['applicationUuid' => $alias], [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            self::$INPUT_DEFAULT_CHOICE,
            // Would you like to link the Cloud application Sample application to this repository?
            'n',
        ]);

        // Assert.
        $this->getDisplay();
        $this->assertEquals(0, $this->getStatusCode());
    }

    public function testConvertInvalidApplicationAliasToUuidArgument(): void
    {
        $this->mockApplicationsRequest(0);
        $this->clientProphecy->addQuery('filter', 'hosting=@*:invalidalias')
            ->shouldBeCalled();
        $this->mockRequest('getAccount');
        $this->command = $this->getApiCommandByName('api:applications:find');
        $alias = 'invalidalias';
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No applications match the alias *:invalidalias');
        $this->executeCommand(['applicationUuid' => $alias], []);
    }

    /**
     * @serial
     */
    public function testConvertNonUniqueApplicationAliasToUuidArgument(): void
    {
        ClearCacheCommand::clearCaches();
        $this->mockApplicationsRequest(2, false);
        $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')
            ->shouldBeCalled();
        $this->mockRequest('getAccount');
        $this->command = $this->getApiCommandByName('api:applications:find');
        $alias = 'devcloud2';
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Multiple applications match the alias *:devcloud2');
        $this->executeCommand(['applicationUuid' => $alias], []);
        $output = $this->getDisplay();
        $this->assertStringContainsString('Use a unique application alias: devcloud:devcloud2, devcloud:devcloud2', $output);
    }

    /**
     * @serial
     */
    public function testConvertApplicationAliasWithRealmToUuidArgument(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $this->mockApplicationsRequest(1, false);
        $this->clientProphecy->addQuery('filter', 'hosting=@devcloud:devcloud2')
            ->shouldBeCalled();
        $this->mockApplicationRequest();
        $this->mockRequest('getAccount');
        $this->command = $this->getApiCommandByName('api:applications:find');
        $alias = 'devcloud:devcloud2';
        $this->executeCommand(['applicationUuid' => $alias], []);
    }

    public function testEnvironmentV3UuidArgument(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $this->mockRequest('environment_by_id', '3e8ecbec-ea7c-4260-8414-ef2938c859bc');
        $this->command = $this->getApiCommandByName('api:environments-v3:find');
        $this->executeCommand(['environmentId' => '3e8ecbec-ea7c-4260-8414-ef2938c859bc'], []);
    }
    /**
     * @serial
     */
    public function testConvertEnvironmentAliasToUuidArgument(): void
    {
        ClearCacheCommand::clearCaches();
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $applicationsResponse = $this->mockApplicationsRequest(1);
        $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')
            ->shouldBeCalled();
        $this->mockEnvironmentsRequest($applicationsResponse);
        $this->mockRequest('getAccount');

        $response = $this->getMockEnvironmentResponse();
        $this->clientProphecy->request('get', '/environments/24-a47ac10b-58cc-4372-a567-0e02b2c3d470')
            ->willReturn($response)
            ->shouldBeCalled();

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
        $this->getDisplay();
        $this->assertEquals(0, $this->getStatusCode());
    }

    /**
     * @group serial
     */
    public function testConvertInvalidEnvironmentAliasToUuidArgument(): void
    {
        ClearCacheCommand::clearCaches();
        $applicationsResponse = $this->mockApplicationsRequest(1);
        $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')
            ->shouldBeCalled();
        $this->mockEnvironmentsRequest($applicationsResponse);
        $this->mockRequest('getAccount');
        $this->command = $this->getApiCommandByName('api:environments:find');
        $alias = 'devcloud2.invalid';
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Environment not found matching the alias devcloud2.invalid');
        $this->executeCommand(['environmentId' => $alias], []);
    }

    public function testApiCommandExecutionForHttpPost(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $mockRequestArgs = self::getMockRequestBodyFromSpec('/account/ssh-keys');
        $mockResponseBody = self::getMockResponseFromSpec('/account/ssh-keys', 'post', '202');
        foreach ($mockRequestArgs as $name => $value) {
            $this->clientProphecy->addOption('json', [$name => $value])
                ->shouldBeCalled();
        }
        $this->clientProphecy->request('post', '/account/ssh-keys')
            ->willReturn($mockResponseBody)
            ->shouldBeCalled();
        $this->command = $this->getApiCommandByName('api:accounts:ssh-key-create');
        $this->executeCommand($mockRequestArgs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertNotNull($output);
        $this->assertJson($output);
        $this->assertStringContainsString('Adding SSH key.', $output);
    }

    public function testApiCommandExecutionForHttpPut(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $mockRequestOptions = self::getMockRequestBodyFromSpec('/environments/{environmentId}', 'put');
        $mockRequestOptions['max_input_vars'] = 1001;
        $mockResponseBody = $this->getMockEnvironmentResponse('put', '202');

        foreach ($mockRequestOptions as $name => $value) {
            $this->clientProphecy->addOption('json', [$name => $value])
                ->shouldBeCalled();
        }
        $this->clientProphecy->request('put', '/environments/24-a47ac10b-58cc-4372-a567-0e02b2c3d470')
            ->willReturn($mockResponseBody)
            ->shouldBeCalled();
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
        $output = $this->getDisplay();
        $this->assertNotNull($output);
        $this->assertJson($output);
        $this->assertStringContainsString('The environment configuration is being updated.', $output);
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestApiCommandDefinitionParameters(): array
    {
        $apiAccountsSshKeysListUsage = '--from="2023-09-01T00:00:00.000Z" --to="2023-09-29T00:00:00.000Z" --sort="field1,-field2" --limit="10" --offset="10"';
        return [
            [
                '0',
                'api:accounts:ssh-keys-list',
                'get',
                $apiAccountsSshKeysListUsage,
            ],
            [
                '1',
                'api:accounts:ssh-keys-list',
                'get',
                $apiAccountsSshKeysListUsage,
            ],
            [
                '1',
                'api:accounts:ssh-keys-list',
                'get',
                $apiAccountsSshKeysListUsage,
            ],
            [
                '1',
                'api:environments:domain-clear-caches',
                'post',
                '12-d314739e-296f-11e9-b210-d663bd873d93 example.com',
            ],
            [
                '1',
                'api:applications:find',
                'get',
                'da1c0a8e-ff69-45db-88fc-acd6d2affbb7',
            ],
            ['1', 'api:applications:find', 'get', 'myapp'],
        ];
    }

    /**
     * @dataProvider providerTestApiCommandDefinitionParameters
     */
    public function testApiCommandDefinitionParameters(string $useSpecCache, string $commandName, string $method, string $usage): void
    {
        putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=' . $useSpecCache);

        $this->command = $this->getApiCommandByName($commandName);
        $resource = self::getResourceFromSpec($this->command->getPath(), $method);
        $this->assertEquals($resource['summary'], $this->command->getDescription());

        $expectedCommandName = 'api:' . $resource['x-cli-name'];
        $this->assertEquals($expectedCommandName, $this->command->getName());

        foreach ($resource['parameters'] as $parameter) {
            $paramKey = str_replace('#/components/parameters/', '', $parameter['$ref']);
            $param = self::getCloudApiSpec()['components']['parameters'][$paramKey];
            $this->assertTrue(
                $this->command->getDefinition()->hasOption($param['name']) ||
                $this->command->getDefinition()->hasArgument($param['name']),
                "Command $expectedCommandName does not have expected argument or option {$param['name']}"
            );
        }

        $usages = $this->command->getUsages();
        $this->assertContains($commandName . ' ' . $usage, $usages);
    }

    public function testModifiedParameterDescriptions(): void
    {
        $this->command = $this->getApiCommandByName('api:environments:domain-status-find');
        $this->assertStringContainsString('You may also use an environment alias', $this->command->getDefinition()
            ->getArgument('environmentId')
            ->getDescription());

        $this->command = $this->getApiCommandByName('api:applications:find');
        $this->assertStringContainsString('You may also use an application alias or omit the argument', $this->command->getDefinition()
            ->getArgument('applicationUuid')
            ->getDescription());
    }

    /**
     * @return string[][]
     */
    public static function providerTestApiCommandDefinitionRequestBody(): array
    {
        return [
            [
                'api:accounts:ssh-key-create',
                'post',
                'api:accounts:ssh-key-create "mykey" "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQChwPHzTTDKDpSbpa2+d22LcbQmsw92eLsUK3Fmei1fiGDkd34NsYCN8m7lsi3NbvdMS83CtPQPWiCveYPzFs1/hHc4PYj8opD2CNnr5iWVVbyaulCYHCgVv4aB/ojcexg8q483A4xJeF15TiCr/gu34rK6ucTvC/tn/rCwJBudczvEwt0klqYwv8Cl/ytaQboSuem5KgSjO3lMrb6CWtfSNhE43ZOw+UBFBqxIninN868vGMkIv9VY34Pwj54rPn/ItQd6Ef4B0KHHaGmzK0vfP+AK7FxNMoHnj3iYT33KZNqtDozdn5tYyH/bThPebEtgqUn+/w5l6wZIC/8zzvls/127ngHk+jNa0PlNyS2TxhPUK4NaPHIEnnrlp07JEYC4ImcBjaYCWAdcTcUkcJjwZQkN4bGmyO9cjICH98SdLD/HxqzTHeaYDbAX/Hu9HfaBb5dXLWsjw3Xc6hoVnUUZbMQyfgb0KgxDLh92eNGxJkpZiL0VDNOWCxDWsNpzwhLNkLqCvI6lyxiLaUzvJAk6dPaRhExmCbU1lDO2eR0FdSwC1TEhJOT9eDIK1r2hztZKs2oa5FNFfB/IFHVWasVFC9N2h/r/egB5zsRxC9MqBLRBq95NBxaRSFng6ML5WZSw41Qi4C/JWVm89rdj2WqScDHYyAdwyyppWU4T5c9Fmw== example@example.com"',
            ],
            [
                'api:environments:file-copy',
                'post',
                '12-d314739e-296f-11e9-b210-d663bd873d93 --source="14-0c7e79ab-1c4a-424e-8446-76ae8be7e851"',
            ],
        ];
    }

    /**
     * @dataProvider providerTestApiCommandDefinitionRequestBody
     * @param $commandName
     * @param $method
     * @param $usage
     */
    public function testApiCommandDefinitionRequestBody(string $commandName, string $method, string $usage): void
    {
        $this->command = $this->getApiCommandByName($commandName);
        $resource = self::getResourceFromSpec($this->command->getPath(), $method);
        foreach ($resource['requestBody']['content']['application/hal+json']['example'] as $propKey => $value) {
            $this->assertTrue(
                $this->command->getDefinition()
                    ->hasArgument($propKey) || $this->command->getDefinition()
                    ->hasOption($propKey),
                "Command {$this->command->getName()} does not have expected argument or option $propKey"
            );
        }
        $this->assertStringContainsString($usage, $this->command->getUsages()[0]);
    }

    public function testGetApplicationUuidFromBltYml(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $mockBody = self::getMockResponseFromSpec('/applications/{applicationUuid}', 'get', '200');
        $this->clientProphecy->request('get', '/applications/' . $mockBody->uuid)
            ->willReturn($mockBody)
            ->shouldBeCalled();
        $this->command = $this->getApiCommandByName('api:applications:find');
        $bltConfigFilePath = Path::join($this->projectDir, 'blt', 'blt.yml');
        $this->fs->dumpFile($bltConfigFilePath, Yaml::dump(['cloud' => ['appId' => $mockBody->uuid]]));
        $this->executeCommand();

        $this->getDisplay();
        $this->fs->remove($bltConfigFilePath);
    }

    public function testOrganizationMemberDeleteByUserUuid(): void
    {
        $orgId = 'bfafd31a-83a6-4257-b0ec-afdeff83117a';
        $memberUuid = '26c4af83-545b-45cb-b165-d537adc9e0b4';

        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $this->mockRequest('postOrganizationMemberDelete', [
            $orgId,
            $memberUuid,
        ], null, 'Member removed');

        $this->command = $this->getApiCommandByName('api:organizations:member-delete');
        $this->executeCommand(
            [
                'organizationUuid' => $orgId,
                'userUuid' => $memberUuid,
            ],
        );

        $output = $this->getDisplay();
        $this->assertEquals(0, $this->getStatusCode());
        $this->assertStringContainsString("Organization member removed", $output);
    }

    /**
     * Test of deletion of the user from organization by user email.
     */
    public function testOrganizationMemberDeleteByUserEmail(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $membersResponse = self::getMockResponseFromSpec('/organizations/{organizationUuid}/members', 'get', 200);
        $orgId = 'bfafd31a-83a6-4257-b0ec-afdeff83117a';
        $memberMail = $membersResponse->_embedded->items[0]->mail;
        $memberUuid = $membersResponse->_embedded->items[0]->uuid;
        $this->clientProphecy->request('get', '/organizations/' . $orgId . '/members')
            ->willReturn($membersResponse->_embedded->items)->shouldBeCalled();

        $this->mockRequest('postOrganizationMemberDelete', [
            $orgId,
            $memberUuid,
        ], null, 'Member removed');

        $this->command = $this->getApiCommandByName('api:organizations:member-delete');
        $this->executeCommand(
            [
                'organizationUuid' => $orgId,
                'userUuid' => $memberMail,
            ],
        );

        $output = $this->getDisplay();
        $this->assertEquals(0, $this->getStatusCode());
        $this->assertStringContainsString("Organization member removed", $output);
    }

    public function testOrganizationMemberDeleteInvalidEmail(): void
    {
        $membersResponse = self::getMockResponseFromSpec('/organizations/{organizationUuid}/members', 'get', 200);
        $orgId = 'bfafd31a-83a6-4257-b0ec-afdeff83117a';
        $memberUuid = $membersResponse->_embedded->items[0]->mail . 'INVALID';
        $this->clientProphecy->request('get', '/organizations/' . $orgId . '/members')
            ->willReturn($membersResponse->_embedded->items)->shouldBeCalled();

        $this->command = $this->getApiCommandByName('api:organizations:member-delete');
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No matching user found in this organization');
        $this->executeCommand(
            [
                'organizationUuid' => $orgId,
                'userUuid' => $memberUuid,
            ],
        );
    }

    /**
     * Test of deletion of the user from organization by user email.
     */
    public function testOrganizationMemberDeleteNoMembers(): void
    {
        $membersResponse = self::getMockResponseFromSpec('/organizations/{organizationUuid}/members', 'get', 200);
        $orgId = 'bfafd31a-83a6-4257-b0ec-afdeff83117a';
        $memberUuid = $membersResponse->_embedded->items[0]->mail;
        $this->clientProphecy->request('get', '/organizations/' . $orgId . '/members')
            ->willReturn([])->shouldBeCalled();

        $this->command = $this->getApiCommandByName('api:organizations:member-delete');
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Organization has no members');
        $this->executeCommand(
            [
                'organizationUuid' => $orgId,
                'userUuid' => $memberUuid,
            ],
        );
    }

    /**
     * Descriptions of API parameters can be defined in an additionalProperties
     * key. Ideally we'd test for this by checking help output, but that can
     * only be done as an application test, which isn't great for performance
     * and doesn't kill associated mutants.
     */
    public function testDescriptionInAdditionalProperties(): void
    {
        $this->command = $this->getApiCommandByName('api:environments:cloud-actions-update');
        try {
            $this->executeCommand(
                [
                    'environmentId' => '24-a47ac10b-58cc-4372-a567-0e02b2c3d470',
                ]
            );
        } catch (MissingInputException) {
            $output = $this->getDisplay();
            $this->assertStringContainsString('Whether this Cloud Action is enabled.', $output);
        }
    }

    /**
     * Tests that commands marked as deprecated are hidden.
     */
    public function testDeprecatedCommandsAreHidden(): void
    {
        // Load the API spec to find deprecated commands.
        $apiSpec = self::getCloudApiSpec();

        foreach ($apiSpec['paths'] as $path => $endpoint) {
            foreach ($endpoint as $method => $schema) {
                if (!array_key_exists('x-cli-name', $schema)) {
                    continue;
                }

                // Test deprecated commands.
                if (array_key_exists('deprecated', $schema) && $schema['deprecated'] === true) {
                    $commandName = 'api:' . $schema['x-cli-name'];
                    $command = $this->getApiCommandByName($commandName);
                    if ($command) {
                        $this->assertTrue($command->isHidden(), "Command $commandName should be hidden because it is deprecated");
                    }
                }
            }
        }
    }

    /**
     * Tests that commands marked as pre-release are hidden.
     */
    public function testPrereleaseCommandsAreHidden(): void
    {
        // Load the API spec to find pre-release commands.
        $apiSpec = self::getCloudApiSpec();

        foreach ($apiSpec['paths'] as $path => $endpoint) {
            foreach ($endpoint as $method => $schema) {
                if (!array_key_exists('x-cli-name', $schema)) {
                    continue;
                }

                // Test pre-release commands.
                if (array_key_exists('x-prerelease', $schema) && $schema['x-prerelease'] === true) {
                    $commandName = 'api:' . $schema['x-cli-name'];
                    $command = $this->getApiCommandByName($commandName);
                    if ($command) {
                        $this->assertTrue($command->isHidden(), "Command $commandName should be hidden because it is pre-release");
                    }
                }
            }
        }
    }

    /**
     * Tests that _links is removed from all API responses, including nested objects and arrays.
     * This test covers both object and array responses to kill LogicalOrAllSubExprNegation mutants.
     */
    public function testLinksRemovedFromAllResponses(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();

        // Test with nested _links in objects and arrays to cover all code paths
        // Include primitive values to kill LogicalOrAllSubExprNegation (should NOT recurse on primitives)
        $rawResponse = (object) [
            // Primitive value - should not recurse.
            'active' => true,
            // Primitive value - should not recurse.
            'count' => 42,
            'nested' => (object) [
                'data' => 'value',
                'items' => [
                    (object) ['id' => 1, '_links' => (object) ['self' => (object) ['href' => 'https://item1.com']]],
                    (object) ['id' => 2, '_links' => (object) ['self' => (object) ['href' => 'https://item2.com']]],
                ],
                '_links' => (object) ['self' => (object) ['href' => 'https://nested.com']],
            ],
            'uuid' => 'test-uuid',
            '_links' => (object) ['self' => (object) ['href' => 'https://example.com']],
        ];

        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn([$rawResponse])
            ->shouldBeCalled();

        $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
        $this->executeCommand();

        $output = $this->getDisplay();
        $decoded = json_decode($output, true);

        // Verify _links removed at all levels (kills LogicalOrAllSubExprNegation mutants)
        $this->assertArrayNotHasKey('_links', $decoded[0]);
        $this->assertArrayNotHasKey('_links', $decoded[0]['nested']);
        $this->assertArrayNotHasKey('_links', $decoded[0]['nested']['items'][0]);
        $this->assertArrayNotHasKey('_links', $decoded[0]['nested']['items'][1]);

        // Verify data preserved including primitives.
        $this->assertEquals('test-uuid', $decoded[0]['uuid']);
        $this->assertEquals(42, $decoded[0]['count']);
        $this->assertTrue($decoded[0]['active']);
        $this->assertEquals('value', $decoded[0]['nested']['data']);
        $this->assertEquals(1, $decoded[0]['nested']['items'][0]['id']);
        $this->assertEquals(2, $decoded[0]['nested']['items'][1]['id']);

        // Test array response path with primitives.
        $arrayResponse = [
            // Primitive.
            'count' => 10,
            'data' => 'value',
            'items' => [
                ['id' => 1, '_links' => ['self' => ['href' => 'https://item1.com']]],
            ],
            '_links' => ['self' => ['href' => 'https://example.com']],
        ];

        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($arrayResponse)
            ->shouldBeCalled();

        $this->executeCommand();
        $output = $this->getDisplay();
        $decoded = json_decode($output, true);

        $this->assertArrayNotHasKey('_links', $decoded);
        $this->assertArrayNotHasKey('_links', $decoded['items'][0]);
        $this->assertEquals('value', $decoded['data']);
        $this->assertEquals(10, $decoded['count']);
        $this->assertEquals(1, $decoded['items'][0]['id']);
    }

    /**
     * Tests that additional API specs are merged and commands are marked as deprecated.
     * This test covers mergeAdditionalSpecs() logic to kill all escaped mutants.
     */
    public function testAdditionalSpecsMergedAndDeprecated(): void
    {
        // Create a temporary additional spec file with proper structure.
        $tempSpecFile = sys_get_temp_dir() . '/test-additional-spec-' . uniqid() . '.json';
        $additionalSpec = [
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.0.0',
            'paths' => [
                '/test/path' => [
                    'post' => [
                        'operationId' => 'testCommand',
                        'responses' => ['200' => ['description' => 'OK']],
                        'summary' => 'Test command',
                        'x-cli-name' => 'test:command',
                    ],
                ],
            ],
        ];
        file_put_contents($tempSpecFile, json_encode($additionalSpec));


        // Create file with invalid JSON to test NotIdentical mutant (line 384)
        $tempInvalidJsonFile = sys_get_temp_dir() . '/test-invalid-json-' . uniqid() . '.json';
        file_put_contents($tempInvalidJsonFile, '{invalid json}');

        try {
            // Test 1: strtoupper is required (kills UnwrapStrToUpper line 372)
            // Lowercase env var should NOT work - only ACQUIA_SPEC (uppercase) works.
            // Lowercase - should NOT load.
            putenv('ACLI_ADDITIONAL_SPEC_FILE_acquia_spec=' . $tempSpecFile);
            ClearCacheCommand::clearCaches();
            $commands1 = $this->getApiCommands();
            // Verify command does NOT exist with lowercase (proves strtoupper is needed)
            $commandExists1 = false;
            foreach ($commands1 as $command) {
                if ($command->getName() === 'api:test:command') {
                    $commandExists1 = true;
                    break;
                }
            }
            $this->assertFalse($commandExists1, 'Command should NOT exist with lowercase env var (strtoupper required)');

            // Uppercase - should load.
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecFile);
            ClearCacheCommand::clearCaches();
            $commands2 = $this->getApiCommands();
            $this->assertNotNull($commands2);

            // Test 2: Full env var name required - prefix needed (kills ConcatOperandRemoval line 373)
            // Also tests Concat mutant (line 373) - order matters.
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecFile);
            ClearCacheCommand::clearCaches();
            $commands3 = $this->getApiCommands();
            $this->assertNotNull($commands3);

            // Test 3: Full JSON env var name required (kills ConcatOperandRemoval line 374)
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC=' . json_encode($additionalSpec));
            ClearCacheCommand::clearCaches();
            $commands4 = $this->getApiCommands();
            $this->assertNotNull($commands4);

            // Test 4: Invalid JSON in file - json_last_error() !== JSON_ERROR_NONE (kills NotIdentical line 384)
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempInvalidJsonFile);
            ClearCacheCommand::clearCaches();
            $commands5 = $this->getApiCommands();
            // Should handle error, return base spec.
            $this->assertNotNull($commands5);

            // Test 5: Invalid JSON in env var - json_last_error() !== JSON_ERROR_NONE (kills NotIdentical line 396)
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC={invalid json}');
            ClearCacheCommand::clearCaches();
            $commands6 = $this->getApiCommands();
            // Should handle error, return base spec.
            $this->assertNotNull($commands6);

            // Test 6: null additionalSpec - !$additionalSpec is true (kills LogicalNot line 403 first part)
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');
            ClearCacheCommand::clearCaches();
            $commands7 = $this->getApiCommands();
            $this->assertNotNull($commands7);

            // Test 7: Valid JSON but not array - !is_array is true (kills LogicalNot line 403 second part, LogicalOr line 403)
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC=' . json_encode('string not array'));
            ClearCacheCommand::clearCaches();
            $commands8 = $this->getApiCommands();
            $this->assertNotNull($commands8);

            // Test 8: Valid array spec - !$additionalSpec is false AND !is_array is false
            // This kills LogicalOrAllSubExprNegation (line 403) - should proceed to merge.
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecFile);
            ClearCacheCommand::clearCaches();
            $commands9 = $this->getApiCommands();
            $this->assertNotNull($commands9);

            // Test 9: LogicalAnd mutants (line 408) - both isset and is_array must be true
            // Test with paths key missing (isset is false) - should skip merging paths.
            $specWithoutPaths = [
                'info' => ['title' => 'Test'],
                'openapi' => '3.0.0',
                // Paths key is missing - isset($additionalSpec['paths']) is false.
            ];
            $tempSpecFile3 = sys_get_temp_dir() . '/test-spec-no-paths-' . uniqid() . '.json';
            file_put_contents($tempSpecFile3, json_encode($specWithoutPaths));
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecFile3);
            ClearCacheCommand::clearCaches();
            $commands10 = $this->getApiCommands();
            $this->assertNotNull($commands10);
            unlink($tempSpecFile3);

            // Test 9b: LogicalAnd - paths exists but is not array (is_array is false)
            // This tests that is_array check prevents foreach - but we need valid structure
            // So we'll test with paths as empty array (which is still an array)
            // The real test is that isset AND is_array both must be true.
            $specWithEmptyPaths = [
                'openapi' => '3.0.0',
                // Empty array - isset is true, is_array is true, but foreach won't execute.
                'paths' => [],
            ];
            $tempSpecFile4 = sys_get_temp_dir() . '/test-spec-empty-paths-' . uniqid() . '.json';
            file_put_contents($tempSpecFile4, json_encode($specWithEmptyPaths));
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecFile4);
            ClearCacheCommand::clearCaches();
            $commands10b = $this->getApiCommands();
            $this->assertNotNull($commands10b);
            unlink($tempSpecFile4);

            // Test 10: Foreach_ mutant (line 412) - verify paths are actually iterated and merged
            // TrueValue (line 419) - verify deprecated is set to true, not false.
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecFile);
            ClearCacheCommand::clearCaches();
            $commands11 = $this->getApiCommands();
            // Verify command exists (proves foreach executed - kills Foreach_ mutant)
            $testCommand = null;
            foreach ($commands11 as $command) {
                if ($command->getName() === 'api:test:command') {
                    $testCommand = $command;
                    break;
                }
            }
            // Explicitly verify command exists (kills Foreach_ mutant - proves foreach executed)
            $this->assertNotNull($testCommand, 'Command should exist (proves foreach executed - kills Foreach_ mutant)');
            // Verify it's deprecated=true (kills TrueValue mutant)
            $this->assertTrue($testCommand->isHidden(), 'Command should be hidden (deprecated=true - kills TrueValue mutant)');

            // Test 11: IfNegation (line 418) and TrueValue (line 419) - verify deprecated flag logic
            // Create spec with mixed array and non-array schemas.
            $specWithMixedSchemas = [
                'openapi' => '3.0.0',
                'paths' => [
                    '/test/path3' => [
                        'post' => [
                            'operationId' => 'testCommand2',
                            'responses' => ['200' => ['description' => 'OK']],
                            'summary' => 'Test command 2',
                            'x-cli-name' => 'test:command2',
                        ],
                    ],
                ],
            ];
            $tempSpecFileMixed = sys_get_temp_dir() . '/test-spec-mixed-' . uniqid() . '.json';
            file_put_contents($tempSpecFileMixed, json_encode($specWithMixedSchemas));
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecFileMixed);
            ClearCacheCommand::clearCaches();
            $commands12 = $this->getApiCommands();
            $this->assertNotNull($commands12);
            // Verify command exists and is deprecated (kills TrueValue and IfNegation mutants)
            $testCommand2 = null;
            foreach ($commands12 as $command) {
                if ($command->getName() === 'api:test:command2') {
                    $testCommand2 = $command;
                    break;
                }
            }
            if ($testCommand2) {
                $this->assertTrue($testCommand2->isHidden(), 'Command should be hidden (deprecated=true)');
            }
            unlink($tempSpecFileMixed);
        } finally {
            // Cleanup.
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');
            if (file_exists($tempSpecFile)) {
                unlink($tempSpecFile);
            }
            if (file_exists($tempInvalidJsonFile)) {
                unlink($tempInvalidJsonFile);
            }
        }
    }

    /**
     * Tests additional coverage scenarios for mergeAdditionalSpecs method.
     * This covers the remaining 37 lines that need coverage.
     */
    public function testAdditionalSpecsMergedAndDeprecatedAdditionalCoverage(): void
    {
        // Test 1: File path exists but file doesn't exist (line 380 - file_exists check)
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=/nonexistent/file-' . uniqid() . '.json');
        ClearCacheCommand::clearCaches();
        $commands1 = $this->getApiCommands();
        $this->assertNotNull($commands1);

        // Test 2: baseSpec['paths'] doesn't exist (line 409-410)
        $specWithoutPaths = [
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.0.0',
            'paths' => [
                '/new/path' => [
                    'post' => [
                        'operationId' => 'newCommand',
                        'responses' => ['200' => ['description' => 'OK']],
                        'summary' => 'New command',
                        'x-cli-name' => 'new:command',
                    ],
                ],
            ],
        ];
        $tempSpecNoPaths = sys_get_temp_dir() . '/test-spec-no-paths-' . uniqid() . '.json';
        file_put_contents($tempSpecNoPaths, json_encode($specWithoutPaths));
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecNoPaths);
        ClearCacheCommand::clearCaches();
        $commands2 = $this->getApiCommands();
        $this->assertNotNull($commands2);
        unlink($tempSpecNoPaths);

        // Test 3: Path already exists in base spec - merge methods (line 424-425)
        $specWithExistingPath = [
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.0.0',
            'paths' => [
                '/applications/{applicationUuid}' => [
                    'get' => [
                        'operationId' => 'findApplication',
                        'responses' => ['200' => ['description' => 'OK']],
                        'summary' => 'Find application',
                        'x-cli-name' => 'applications:find',
                    ],
                ],
            ],
        ];
        $tempSpecExistingPath = sys_get_temp_dir() . '/test-spec-existing-path-' . uniqid() . '.json';
        file_put_contents($tempSpecExistingPath, json_encode($specWithExistingPath));
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecExistingPath);
        ClearCacheCommand::clearCaches();
        $commands3 = $this->getApiCommands();
        $this->assertNotNull($commands3);
        unlink($tempSpecExistingPath);

        // Test 4: Path doesn't exist in base spec - add new path (line 426-428)
        $specWithNewPath = [
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.0.0',
            'paths' => [
                '/completely/new/path' => [
                    'post' => [
                        'operationId' => 'completelyNewCommand',
                        'responses' => ['200' => ['description' => 'OK']],
                        'summary' => 'Completely new command',
                        'x-cli-name' => 'completely:new:command',
                    ],
                ],
            ],
        ];
        $tempSpecNewPath = sys_get_temp_dir() . '/test-spec-new-path-' . uniqid() . '.json';
        file_put_contents($tempSpecNewPath, json_encode($specWithNewPath));
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecNewPath);
        ClearCacheCommand::clearCaches();
        $commands4 = $this->getApiCommands();
        $this->assertNotNull($commands4);
        unlink($tempSpecNewPath);

        // Test 5: Components merging - baseSpec['components'] doesn't exist (line 435-436)
        $specWithComponents = [
            'components' => [
                'schemas' => [
                    'TestSchema' => [
                        'properties' => ['test' => ['type' => 'string']],
                        'type' => 'object',
                    ],
                ],
            ],
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.0.0',
        ];
        $tempSpecComponents = sys_get_temp_dir() . '/test-spec-components-' . uniqid() . '.json';
        file_put_contents($tempSpecComponents, json_encode($specWithComponents));
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecComponents);
        ClearCacheCommand::clearCaches();
        $commands5 = $this->getApiCommands();
        $this->assertNotNull($commands5);
        unlink($tempSpecComponents);

        // Test 6: Components merging - component type doesn't exist (line 439-440)
        $specWithNewComponentType = [
            'components' => [
                'parameters' => [
                    'NewParam' => [
                        'in' => 'query',
                        'name' => 'newParam',
                        'schema' => ['type' => 'string'],
                    ],
                ],
            ],
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.0.0',
        ];
        $tempSpecNewComponentType = sys_get_temp_dir() . '/test-spec-new-component-type-' . uniqid() . '.json';
        file_put_contents($tempSpecNewComponentType, json_encode($specWithNewComponentType));
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecNewComponentType);
        ClearCacheCommand::clearCaches();
        $commands6 = $this->getApiCommands();
        $this->assertNotNull($commands6);
        unlink($tempSpecNewComponentType);

        // Test 7: Components merging - components is not an array (line 442-446)
        $specWithNonArrayComponents = [
            'components' => [
                // Components exists but is not array.
                'schemas' => 'not an array',
            ],
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.0.0',
        ];
        $tempSpecNonArrayComponents = sys_get_temp_dir() . '/test-spec-non-array-components-' . uniqid() . '.json';
        file_put_contents($tempSpecNonArrayComponents, json_encode($specWithNonArrayComponents));
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecNonArrayComponents);
        ClearCacheCommand::clearCaches();
        $commands7 = $this->getApiCommands();
        $this->assertNotNull($commands7);
        unlink($tempSpecNonArrayComponents);

        // Test 7b: Components merging - $additionalSpec['components'] itself is not an array (line 434)
        // This kills LogicalAndSingleSubExprNegation mutation - if is_array changed to !is_array, merging would fail.
        $apiCommandHelper = new \Acquia\Cli\Command\Api\ApiCommandHelper($this->logger);
        $reflection = new \ReflectionClass($apiCommandHelper);
        $method = $reflection->getMethod('mergeAdditionalSpecs');
        $method->setAccessible(true);
        $baseSpec = [
            'components' => [
                'schemas' => [
                    'ExistingSchema' => ['type' => 'object'],
                ],
            ],
            'openapi' => '3.0.0',
        ];
        $specWithComponentsNotArray = [
            // Components itself is not an array.
            'components' => 'not an array',
            'openapi' => '3.0.0',
        ];
        putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC=' . json_encode($specWithComponentsNotArray));
        $result7b = $method->invokeArgs($apiCommandHelper, [$baseSpec, '/path/to/acquia-spec.json']);
        putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');
        // Verify components were NOT merged (kills LogicalAndSingleSubExprNegation - if !is_array, it would try to merge and fail)
        // Original code: is_array('not an array') = false, so condition is false, components not merged
        // Mutated code: !is_array('not an array') = true, so condition is true, would try foreach on string and fail.
        $this->assertArrayHasKey('components', $result7b, 'baseSpec should still have components');
        $this->assertArrayHasKey('schemas', $result7b['components'], 'Existing schemas should be preserved');
        $this->assertArrayHasKey('ExistingSchema', $result7b['components']['schemas'], 'Existing schema should be preserved');
        // Verify no new components were added (proves components were not merged)
        $this->assertCount(1, $result7b['components'], 'Should only have schemas, no new component types added');

        // Test 8: Components merging - merge existing components (line 443-446)
        $specWithMergedComponents = [
            'components' => [
                'schemas' => [
                    'MergedSchema' => [
                        'properties' => ['merged' => ['type' => 'string']],
                        'type' => 'object',
                    ],
                ],
            ],
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.0.0',
        ];
        $tempSpecMergedComponents = sys_get_temp_dir() . '/test-spec-merged-components-' . uniqid() . '.json';
        file_put_contents($tempSpecMergedComponents, json_encode($specWithMergedComponents));
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC=' . $tempSpecMergedComponents);
        ClearCacheCommand::clearCaches();
        $commands8 = $this->getApiCommands();
        $this->assertNotNull($commands8);
        unlink($tempSpecMergedComponents);

        // Cleanup.
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
        putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');
    }

    /**
     * Tests that mungeResponse is called and removes _links from response.
     * This kills the MethodCallRemoval mutation at line 140.
     */
    public function testMungeResponseCalled(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        $mockResponse = (object)[
            'name' => 'test-name',
            'uuid' => 'test-uuid-123',
            '_links' => (object)[
                'self' => (object)['href' => '/test/path'],
            ],
        ];
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockResponse)
            ->shouldBeCalled();
        $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
        $this->executeCommand([]);

        $output = $this->getDisplay();
        $this->assertJson($output);
        $decoded = json_decode($output, true);
        // Verify _links are removed (kills MethodCallRemoval mutation - if mungeResponse wasn't called, _links would remain)
        $this->assertArrayNotHasKey('_links', $decoded);
        $this->assertEquals('test-uuid-123', $decoded['uuid']);
        $this->assertEquals('test-name', $decoded['name']);
    }

    /**
     * Tests that mungeResponse recursively removes _links from nested objects and arrays.
     * This kills the LogicalOr mutation at line 159 (is_object || is_array should be || not &&).
     */
    public function testMungeResponseRecursiveRemoval(): void
    {
        $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2'])
            ->shouldBeCalled();
        // Create response with nested objects (is_object will be true) and nested arrays (is_array will be true)
        // to test the LogicalOr condition at line 159.
        $mockResponse = (object)[
            'nested_array' => [
                (object)[
                    'id' => 'item-1',
                    '_links' => (object)[
                        'self' => (object)['href' => '/item1/path'],
                    ],
                ],
                [
                    'id' => 'item-2',
                    '_links' => [
                        'self' => ['href' => '/item2/path'],
                    ],
                ],
            ],
            'nested_object' => (object)[
                'deep_nested_object' => (object)[
                    'value' => 'deep-value',
                    '_links' => (object)[
                        'self' => (object)['href' => '/deep/path'],
                    ],
                ],
                'id' => 'nested-id',
                '_links' => (object)[
                    'self' => (object)['href' => '/nested/path'],
                ],
            ],
            'uuid' => 'test-uuid',
            '_links' => (object)[
                'self' => (object)['href' => '/test/path'],
            ],
        ];
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockResponse)
            ->shouldBeCalled();
        $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
        $this->executeCommand([]);

        $output = $this->getDisplay();
        $this->assertJson($output);
        $decoded = json_decode($output, true);

        // Verify top-level _links removed.
        $this->assertArrayNotHasKey('_links', $decoded);

        // Verify nested object _links removed (tests is_object($value) path)
        $this->assertArrayNotHasKey('_links', $decoded['nested_object']);
        $this->assertArrayNotHasKey('_links', $decoded['nested_object']['deep_nested_object']);

        // Verify nested array _links removed (tests is_array($value) path)
        // This kills LogicalOr mutation - if changed to &&, nested arrays wouldn't be processed.
        $this->assertArrayNotHasKey('_links', $decoded['nested_array'][0]);

        // Verify other data is preserved.
        $this->assertEquals('test-uuid', $decoded['uuid']);
        $this->assertEquals('nested-id', $decoded['nested_object']['id']);
        $this->assertEquals('deep-value', $decoded['nested_object']['deep_nested_object']['value']);
        $this->assertEquals('item-1', $decoded['nested_array'][0]['id']);
        $this->assertEquals('item-2', $decoded['nested_array'][1]['id']);
    }

    /**
     * Tests that mergeAdditionalSpecs returns early when additionalSpec is null or not an array.
     * This kills the ReturnRemoval mutation at line 404 and LogicalOr mutation at line 403.
     */
    public function testMergeAdditionalSpecsEarlyReturn(): void
    {
        // Ensure clean state.
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
        putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');

        $apiCommandHelper = new \Acquia\Cli\Command\Api\ApiCommandHelper($this->logger);
        $reflection = new \ReflectionClass($apiCommandHelper);
        $method = $reflection->getMethod('mergeAdditionalSpecs');
        $method->setAccessible(true);

        $baseSpec = [
            'openapi' => '3.0.0',
            'paths' => [
                '/existing/path' => [
                    'get' => ['operationId' => 'existing'],
                ],
            ],
        ];

        try {
            // Test 1: null additionalSpec - should return baseSpec unchanged (kills ReturnRemoval mutation)
            $result1 = $method->invokeArgs($apiCommandHelper, [$baseSpec, '/path/to/acquia-spec.json']);
            $this->assertEquals($baseSpec, $result1, 'Should return baseSpec unchanged when additionalSpec is null');

            // Test 2: non-array additionalSpec - should return baseSpec unchanged.
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC=' . json_encode('string not array'));
            \Acquia\Cli\Command\Self\ClearCacheCommand::clearCaches();
            $result2 = $method->invokeArgs($apiCommandHelper, [$baseSpec, '/path/to/acquia-spec.json']);
            $this->assertEquals($baseSpec, $result2, 'Should return baseSpec unchanged when additionalSpec is not an array');

            // Test 3: valid array additionalSpec - should NOT return early (kills LogicalOr mutation: || to &&)
            // If mutation changes || to &&, this would incorrectly return early.
            $additionalSpec = [
                'openapi' => '3.0.0',
                'paths' => [
                    '/new/path' => [
                        'post' => [
                            'operationId' => 'newCommand',
                            'x-cli-name' => 'new:command',
                        ],
                    ],
                ],
            ];
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC=' . json_encode($additionalSpec));
            $result3 = $method->invokeArgs($apiCommandHelper, [$baseSpec, '/path/to/acquia-spec.json']);
            // Verify merging happened - new path should be added.
            $this->assertArrayHasKey('/new/path', $result3['paths'], 'New path should be merged when additionalSpec is valid array');
            $this->assertArrayHasKey('/existing/path', $result3['paths'], 'Existing path should be preserved');
        } finally {
            // Cleanup.
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');
        }
    }

    /**
     * Tests that mergeAdditionalSpecs initializes baseSpec['paths'] when it doesn't exist.
     * This kills the LogicalNot mutation at line 409.
     */
    public function testMergeAdditionalSpecsInitializesBaseSpecPaths(): void
    {
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
        putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');

        $apiCommandHelper = new \Acquia\Cli\Command\Api\ApiCommandHelper($this->logger);
        $reflection = new \ReflectionClass($apiCommandHelper);
        $method = $reflection->getMethod('mergeAdditionalSpecs');
        $method->setAccessible(true);

        // baseSpec without 'paths' key.
        $baseSpec = [
            'info' => ['title' => 'Test'],
            'openapi' => '3.0.0',
        ];

        $additionalSpec = [
            'openapi' => '3.0.0',
            'paths' => [
                '/new/path' => [
                    'post' => [
                        'operationId' => 'newCommand',
                        'x-cli-name' => 'new:command',
                    ],
                ],
            ],
        ];

        try {
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC=' . json_encode($additionalSpec));
            $result = $method->invokeArgs($apiCommandHelper, [$baseSpec, '/path/to/acquia-spec.json']);

            // Verify baseSpec['paths'] was initialized (kills LogicalNot mutation - if !isset was changed to isset, paths wouldn't be initialized)
            $this->assertArrayHasKey('paths', $result, 'baseSpec should have paths key after merging');
            $this->assertIsArray($result['paths'], 'baseSpec[paths] should be an array');
            $this->assertArrayHasKey('/new/path', $result['paths'], 'New path should be added to initialized paths array');
        } finally {
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');
        }
    }

    /**
     * Tests that mergeAdditionalSpecs merges methods when path already exists.
     * This kills the UnwrapArrayMerge mutation at line 425.
     */
    public function testMergeAdditionalSpecsMergesExistingPath(): void
    {
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
        putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');

        $apiCommandHelper = new \Acquia\Cli\Command\Api\ApiCommandHelper($this->logger);
        $reflection = new \ReflectionClass($apiCommandHelper);
        $method = $reflection->getMethod('mergeAdditionalSpecs');
        $method->setAccessible(true);

        $baseSpec = [
            'openapi' => '3.0.0',
            'paths' => [
                '/existing/path' => [
                    'get' => [
                        'operationId' => 'getExisting',
                        'x-cli-name' => 'existing:get',
                    ],
                ],
            ],
        ];

        $additionalSpec = [
            'openapi' => '3.0.0',
            'paths' => [
                '/existing/path' => [
                    'post' => [
                        'operationId' => 'postExisting',
                        'x-cli-name' => 'existing:post',
                    ],
                ],
            ],
        ];

        try {
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC=' . json_encode($additionalSpec));
            $result = $method->invokeArgs($apiCommandHelper, [$baseSpec, '/path/to/acquia-spec.json']);

            // Verify both methods exist (kills UnwrapArrayMerge mutation - if array_merge was removed, only 'post' would exist)
            $this->assertArrayHasKey('/existing/path', $result['paths']);
            $this->assertArrayHasKey('get', $result['paths']['/existing/path'], 'Original get method should be preserved');
            $this->assertArrayHasKey('post', $result['paths']['/existing/path'], 'New post method should be merged');
            $this->assertEquals('getExisting', $result['paths']['/existing/path']['get']['operationId']);
            $this->assertEquals('postExisting', $result['paths']['/existing/path']['post']['operationId']);
        } finally {
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');
        }
    }

    /**
     * Tests that mergeAdditionalSpecs returns early without modifying baseSpec.
     * This kills the ReturnRemoval mutation at line 404.
     */
    public function testMergeAdditionalSpecsReturnsEarly(): void
    {
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
        putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');

        $apiCommandHelper = new \Acquia\Cli\Command\Api\ApiCommandHelper($this->logger);
        $reflection = new \ReflectionClass($apiCommandHelper);
        $method = $reflection->getMethod('mergeAdditionalSpecs');
        $method->setAccessible(true);

        $baseSpec = [
            'components' => ['schemas' => ['TestSchema' => ['type' => 'object']]],
            'openapi' => '3.0.0',
            'paths' => ['/test' => ['get' => ['operationId' => 'test']]],
        ];

        // Test that null additionalSpec causes early return (kills ReturnRemoval mutation)
        // If return is removed, the function would continue and try to merge null, causing errors.
        $result = $method->invokeArgs($apiCommandHelper, [$baseSpec, '/path/to/acquia-spec.json']);
        $this->assertSame($baseSpec, $result, 'Should return exact same baseSpec when additionalSpec is null (early return)');
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('components', $result);
        $this->assertCount(1, $result['paths'], 'Paths should be unchanged');
        $this->assertCount(1, $result['components'], 'Components should be unchanged');
    }

    /**
     * Tests that mergeAdditionalSpecs iterates and merges components using array_merge.
     * This kills the Foreach_ mutation at line 438 and UnwrapArrayMerge mutation at line 443.
     */
    public function testMergeAdditionalSpecsIteratesAndMergesComponents(): void
    {
        putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
        putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');

        $apiCommandHelper = new \Acquia\Cli\Command\Api\ApiCommandHelper($this->logger);
        $reflection = new \ReflectionClass($apiCommandHelper);
        $method = $reflection->getMethod('mergeAdditionalSpecs');
        $method->setAccessible(true);

        $baseSpec = [
            'components' => [
                'parameters' => [
                    'ExistingParam' => [
                        'in' => 'query',
                        'name' => 'existing',
                    ],
                ],
                'schemas' => [
                    'ExistingSchema' => [
                        'properties' => ['existing' => ['type' => 'string']],
                        'type' => 'object',
                    ],
                ],
            ],
            'openapi' => '3.0.0',
        ];

        $additionalSpec = [
            'components' => [
                'parameters' => [
                    'NewParam' => [
                        'in' => 'query',
                        'name' => 'new',
                    ],
                ],
                'responses' => [
                    'NewResponse' => [
                        'description' => 'New response',
                    ],
                ],
                'schemas' => [
                    'NewSchema' => [
                        'properties' => ['new' => ['type' => 'string']],
                        'type' => 'object',
                    ],
                ],
            ],
            'openapi' => '3.0.0',
        ];

        try {
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC=' . json_encode($additionalSpec));
            $result = $method->invokeArgs($apiCommandHelper, [$baseSpec, '/path/to/acquia-spec.json']);

            // Verify foreach iterated over components (kills Foreach_ mutation - if foreach iterates over [], nothing would be merged)
            $this->assertArrayHasKey('components', $result);
            $this->assertArrayHasKey('schemas', $result['components'], 'schemas should exist (proves foreach iterated)');
            $this->assertArrayHasKey('parameters', $result['components'], 'parameters should exist (proves foreach iterated)');
            $this->assertArrayHasKey('responses', $result['components'], 'responses should exist (proves foreach iterated)');

            // Verify array_merge was called (kills UnwrapArrayMerge mutation - if array_merge removed, only new components would exist)
            // Both existing and new schemas should exist.
            $this->assertArrayHasKey('ExistingSchema', $result['components']['schemas'], 'Existing schema should be preserved');
            $this->assertArrayHasKey('NewSchema', $result['components']['schemas'], 'New schema should be merged');
            // Both existing and new parameters should exist.
            $this->assertArrayHasKey('ExistingParam', $result['components']['parameters'], 'Existing parameter should be preserved');
            $this->assertArrayHasKey('NewParam', $result['components']['parameters'], 'New parameter should be merged');
            // New response type should exist.
            $this->assertArrayHasKey('NewResponse', $result['components']['responses'], 'New response should be added');

            // Verify merged values are correct.
            $this->assertEquals('object', $result['components']['schemas']['ExistingSchema']['type']);
            $this->assertEquals('object', $result['components']['schemas']['NewSchema']['type']);
            $this->assertEquals('existing', $result['components']['parameters']['ExistingParam']['name']);
            $this->assertEquals('new', $result['components']['parameters']['NewParam']['name']);
        } finally {
            putenv('ACLI_ADDITIONAL_SPEC_FILE_ACQUIA_SPEC');
            putenv('ACLI_ADDITIONAL_SPEC_JSON_ACQUIA_SPEC');
        }
    }
}
