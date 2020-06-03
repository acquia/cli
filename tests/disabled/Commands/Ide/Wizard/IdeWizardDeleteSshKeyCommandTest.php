<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ide\Wizard\IdeWizardDeleteSshKeyCommand;
use AcquiaCloudApi\Response\IdeResponse;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeWizardDeleteSshKeyCommand.
 *
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeWizardDeleteSshKeyCommandTest extends IdeWizardTestBase {

  /**
   * Tests the 'ide:wizard:ssh-key:create' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testDelete(): void {

    // Request for IDE data.
    $ide_response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/ides/' . $this->remote_ide_uuid)->willReturn($ide_response)->shouldBeCalled();
    $ide = new IdeResponse((object) $ide_response);

    // Request for list of SSH keys in Cloud.
    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $mock_body->{'_embedded'}->items[0]->label = $this->command->getIdeSshKeyLabel($ide);
    $this->clientProphecy->request('get', '/account/ssh-keys')->willReturn($mock_body->{'_embedded'}->items)->shouldBeCalled();

    // Request for specific SSH key in Cloud.
    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $mock_body->{'_embedded'}->items[0]->label = $this->command->getIdeSshKeyLabel($ide);
    $this->clientProphecy->request('get', '/account/ssh-keys/' . $mock_body->{'_embedded'}->items[0]->uuid)->willReturn($mock_body->{'_embedded'}->items[0])->shouldBeCalled();

    // Request ssh key deletion.
    $response = $this->prophet->prophesize(ResponseInterface::class);
    $response->getStatusCode()->willReturn(202);
    $this->clientProphecy->makeRequest('delete', '/account/ssh-keys/' . $mock_body->{'_embedded'}->items[0]->uuid)->willReturn($response->reveal())->shouldBeCalled();

    // Create the file so it can be deleted.
    $ssh_key_filename = $this->command->getSshKeyFilename($this->remote_ide_uuid);
    $this->command->getApplication()->setSshKeysDir(sys_get_temp_dir());
    $this->fs->touch($this->command->getApplication()->getSshKeysDir() . '/' . $ssh_key_filename);
    $this->fs->dumpFile($this->command->getApplication()->getSshKeysDir() . '/' . $ssh_key_filename . '.pub', $mock_body->{'_embedded'}->items[0]->public_key);

    // Run it!
    $this->executeCommand([]);

    $this->prophet->checkPredictions();
    $this->assertFileDoesNotExist($this->command->getApplication()->getSshKeysDir() . '/' . $ssh_key_filename);
  }

  /**
   * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
   */
  protected function createCommand(): Command {
    return new IdeWizardDeleteSshKeyCommand();
  }

  // Test can only be run inside IDE.
}
