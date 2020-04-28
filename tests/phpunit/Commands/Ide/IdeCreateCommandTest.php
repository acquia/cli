<?php

namespace Acquia\Ads\Tests\Ide;

use Acquia\Ads\Command\Ide\IdeCreateCommand;
use Acquia\Ads\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeCreateCommandTest
 *
 * @property IdeCreateCommand $command
 * @package Acquia\Ads\Tests\Ide
 */
class IdeCreateCommandTest extends CommandTestBase
{

    /**
     * Tests the 'ide:create' command.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testCreate(): void
    {
        $this->setCommand($this->createCommand());

        /** @var \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client */
        $cloud_client = $this->prophet->prophesize(Client::class);

        // Request for applications.
        $response = $this->getMockResponseFromSpec('/applications', 'get', '200');
        $cloud_client->request('get', '/applications')->willReturn($response->{'_embedded'}->items);

        // Request to create IDE.
        $response = $this->getMockResponseFromSpec('/api/applications/{applicationUuid}/ides', 'post', '200');
        $cloud_client->request(
            'post',
            // @todo Consider replacing path parameter with Argument::containingString('/ides') or something.
            '/applications/a47ac10b-58cc-4372-a567-0e02b2c3d470/ides',
            ['form_params' => ['label' => 'Example IDE']]
        )->willReturn($response);

        $response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
        $cloud_client->request('get', '/ides/215824ff-272a-4a8c-9027-df32ed1d68a9')->willReturn($response);
        
        $this->command->setAcquiaCloudClient($cloud_client->reveal());

        /** @var \Prophecy\Prophecy\ObjectProphecy|\GuzzleHttp\Psr7\Response $guzzle_response */
        $guzzle_response = $this->prophet->prophesize(Response::class);
        $guzzle_response->getStatusCode()->willReturn(200);
        $guzzle_client = $this->prophet->prophesize(\GuzzleHttp\Client::class);
        $guzzle_client->request('GET', '/health')->willReturn($guzzle_response->reveal());
        $this->command->setClient($guzzle_client->reveal());

        $inputs = [
            // @todo Don't assume we're authenticated!
            // Please select the application for which you'd like to create a new IDE
            '0',
            // Please enter a label for your Remote IDE:
            'Example IDE',
        ];

        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();
        $this->assertStringContainsString('Please select the application for which you\'d like to create a new IDE:', $output);
        $this->assertStringContainsString('  [0] Sample application 1', $output);
        $this->assertStringContainsString('  [1] Sample application 2', $output);
        $this->assertStringContainsString('Please enter a label for your Remote IDE:', $output);
        $this->assertStringContainsString('Waiting for DNS to propagate...', $output);
        $this->assertStringContainsString('Your IDE is ready!', $output);
        $this->assertStringContainsString('Your IDE URL: https://215824ff-272a-4a8c-9027-df32ed1d68a9.ides.acquia.com', $output);
        $this->assertStringContainsString('Your Drupal Site URL: https://ide-215824ff-272a-4a8c-9027-df32ed1d68a9.prod.acquia-sites.com', $output);
    }

    /**
     * @return IdeCreateCommand
     */
    protected function createCommand(): Command
    {
        return new IdeCreateCommand();
    }
}
