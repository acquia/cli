<?php


namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyInfoCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;

class SshKeyInfoCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyInfoCommand::class);
  }

  public function testInfo(): void {
    $ssh_key_list_response = $this->mockListSshKeysRequest();

    $response = $this->getMockResponseFromSpec('/account/ssh-keys/{sshKeyUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/account/ssh-keys/' . $ssh_key_list_response->_embedded->items[0]->uuid)->willReturn($response)->shouldBeCalled();

    $inputs = [
      // Choose key.
      '0',
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Choose an SSH key to view', $output);
    $this->assertStringContainsString('SSH key property   SSH key value', $output);
    $this->assertStringContainsString('UUID               b2a53dfb-f4e2-4543-814d-7a9aa3793746', $output);
    $this->assertStringContainsString('Label              PC Home', $output);
    $this->assertStringContainsString('Fingerprint        8d:13:fb:50:50:da:cf:c5:bf:ca:31:33:ed:51:27:24', $output);
    $this->assertStringContainsString('Created at         2017-05-09T20:30:35+00:00', $output);
  }

}
