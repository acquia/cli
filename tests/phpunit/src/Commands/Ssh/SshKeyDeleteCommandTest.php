<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Class SshKeyDeleteCommandTest
 *
 * @property SshKeyDeleteCommand $command
 * @package Acquia\Cli\Tests\Ssh
 */
class SshKeyDeleteCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyDeleteCommand::class);
  }

  /**
   * Tests the 'ssh-key:upload' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException|\JsonException
   */
  public function testDelete(): void {

    $ssh_key_list_response = $this->mockListSshKeysRequest();
    $response = $this->prophet->prophesize(ResponseInterface::class);
    $response->getStatusCode()->willReturn(202);
    $this->getMockResponseFromSpec('/account/ssh-keys/{sshKeyUuid}', 'delete', '202');
    $this->clientProphecy->makeRequest('delete', '/account/ssh-keys/' . $ssh_key_list_response->_embedded->items[0]->uuid)->willReturn($response->reveal())->shouldBeCalled();

    $inputs = [
      // Choose key.
      '0',
      // Do you also want to delete the corresponding local key files?
      'n'
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Choose an SSH key to delete from the Cloud Platform', $output);
    $this->assertStringContainsString($ssh_key_list_response->_embedded->items[0]->label, $output);
  }

}
