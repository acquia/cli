<?php

namespace Acquia\Ads\Tests\Api;

use Acquia\Ads\Command\Api\ApiCommandBase;
use Acquia\Ads\Command\Api\ApiCommandHelper;
use Acquia\Ads\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Client;
use Symfony\Component\Console\Command\Command;

/**
 * Class ApiCommandTest
 * @property ApiCommandBase $command
 * @package Acquia\Ads\Tests\Api
 */
class ApiCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command
    {
        return new ApiCommandBase();
    }

    /**
     * Tests the 'api:*' commands.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testApiCommandWithHttpGet(): void
    {
        /** @var \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client */
        $cloud_client = $this->prophet->prophesize(Client::class);
        $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $cloud_client->request('get', '/account/ssh-keys')->willReturn($mock_body->{'_embedded'}->items);

        $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
        $this->command->setAcquiaCloudClient($cloud_client->reveal());
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertJson($output);

        $contents = json_decode($output, true);
        $this->assertArrayHasKey(0, $contents);
        $this->assertArrayHasKey('uuid', $contents[0]);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testApiCommandWithHttpPost(): void
    {
        /** @var \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client */
        $cloud_client = $this->prophet->prophesize(Client::class);
        $mock_request_body = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
        $options = [
          'form_params' => $mock_request_body,
        ];
        $mock_response_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'post', '202');
        $cloud_client->request('post', '/account/ssh-keys', $options)->willReturn($mock_response_body);
        $this->command = $this->getApiCommandByName('api:accounts:ssh-keys-list');
        $this->command->setAcquiaCloudClient($cloud_client->reveal());
        $this->executeCommand();
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testApiCommandDefinition(): void
    {
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
    }

    // @todo Assert parameters are actually passed to the client. E.g., --limit.

    /**
     * @param $name
     *
     * @return \Acquia\Ads\Command\Api\ApiCommandBase|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getApiCommandByName($name): ?ApiCommandBase
    {
        $api_command_helper = new ApiCommandHelper();
        $api_commands = $api_command_helper->getApiCommands();
        foreach ($api_commands as $api_command) {
            if ($api_command->getName() === $name) {
                return $api_command;
            }
        }

        return null;
    }
}
