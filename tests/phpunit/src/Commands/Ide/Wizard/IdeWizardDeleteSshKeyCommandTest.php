<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ide\Wizard\IdeWizardDeleteSshKeyCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand $command
 */
class IdeWizardDeleteSshKeyCommandTest extends IdeWizardTestBase {

  /**
   * Tests the 'ide:wizard:ssh-key:create' command.
   */
  public function testDelete(): void {
    // Request for IDE data.
    $ide_response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/ides/' . IdeHelper::$remote_ide_uuid)->willReturn($ide_response)->shouldBeCalled();
    $ide = new IdeResponse((object) $ide_response);
    $mock_body = $this->mockListSshKeysRequestWithIdeKey($ide);

    $this->mockDeleteSshKeyRequest($mock_body->{'_embedded'}->items[0]->uuid);

    // Create the file so it can be deleted.
    $ssh_key_filename = $this->command::getSshKeyFilename(IdeHelper::$remote_ide_uuid);
    $this->fs->touch($this->sshDir . '/' . $ssh_key_filename);
    $this->fs->dumpFile($this->sshDir . '/' . $ssh_key_filename . '.pub', $mock_body->{'_embedded'}->items[0]->public_key);

    // Run it!
    $this->executeCommand([]);

    $this->prophet->checkPredictions();
    $this->assertFileDoesNotExist($this->sshDir . '/' . $ssh_key_filename);
  }

  /**
   * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeWizardDeleteSshKeyCommand::class);
  }

}
