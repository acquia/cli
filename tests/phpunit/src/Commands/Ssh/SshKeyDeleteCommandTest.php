<?php

namespace Acquia\Ads\Tests\Commands\Ssh;

use Acquia\Ads\AcquiaCliApplication;
use Acquia\Ads\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Ads\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Class SshKeyDeleteCommandTest
 * @property SshKeyDeleteCommand $command
 * @package Acquia\Ads\Tests\Ssh
 */
class SshKeyDeleteCommandTest extends CommandTestBase
{

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new SshKeyDeleteCommand();
  }

  /**
   * Tests the 'ssh-key:upload' command.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testDelete(): void {
    $this->setCommand($this->createCommand());

    /** @var \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client */
    $cloud_client = $this->prophet->prophesize(Client::class);
    $mock_get_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $cloud_client->request('get', '/account/ssh-keys')->willReturn($mock_get_body->{'_embedded'}->items)->shouldBeCalled();

    $response = $this->prophet->prophesize(ResponseInterface::class);
    $response->getStatusCode()->willReturn(202);
    $mock_delete_body = $this->getMockResponseFromSpec('/account/ssh-keys/{sshKeyUuid}', 'delete', '202');
    $cloud_client->makeRequest('delete', '/account/ssh-keys/' . $mock_get_body->_embedded->items[0]->uuid)->willReturn($response->reveal())->shouldBeCalled();

    $this->application->setAcquiaCloudClient($cloud_client->reveal());
    $inputs = [
      // Choose key.
      '0',
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Choose an SSH key to delete from Acquia Cloud:', $output);
    $this->assertStringContainsString($mock_get_body->_embedded->items[0]->label, $output);
  }

}
