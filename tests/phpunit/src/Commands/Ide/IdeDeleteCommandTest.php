<?php

namespace Acquia\Ads\Tests\Commands\Ide;

use Acquia\Ads\Command\Ide\IdeDeleteCommand;
use Acquia\Ads\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Client;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeDeleteCommandTest
 * @property IdeDeleteCommand $command
 * @package Acquia\Ads\Tests\Ide
 */
class IdeDeleteCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command {
        return new IdeDeleteCommand();
    }

    /**
     * Tests the 'ide:delete' command.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testIdeDeleteCommand(): void {
        $this->setCommand($this->createCommand());

        /** @var \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client */
        $cloud_client = $this->prophet->prophesize(Client::class);

        // Request for applications.
        $response = $this->getMockResponseFromSpec('/applications', 'get', '200');
        $cloud_client->request('get', '/applications')
          ->willReturn($response->{'_embedded'}->items)
          ->shouldBeCalled();

        // Request to list IDEs.
        $response = $this->getMockResponseFromSpec('/api/applications/{applicationUuid}/ides', 'get', '200');
        $cloud_client->request('get', '/applications/a47ac10b-58cc-4372-a567-0e02b2c3d470/ides')
          ->willReturn($response->{'_embedded'}->items)
          ->shouldBeCalled();

        // Request to delete IDE.
        $response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'delete', '202');
        $cloud_client->request(
            'delete',
            '/ides/9a83c081-ef78-4dbd-8852-11cc3eb248f7'
        )->willReturn($response->{"De-provisioning IDE"}->value)
          ->shouldBeCalled();

        $inputs = [
          // Please select the application for which you'd like to create a new IDE
          '0',
          // Please select the IDE you'd like to delete:
          '0',
        ];

        $this->command->setAcquiaCloudClient($cloud_client->reveal());
        $this->executeCommand([], $inputs);

        // Assert.
        $this->prophet->checkPredictions();
        $output = $this->getDisplay();
        $this->assertStringContainsString('The remote IDE is being deleted.', $output);
    }

}
