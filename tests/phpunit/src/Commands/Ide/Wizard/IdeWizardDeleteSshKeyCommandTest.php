<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ide\Wizard\IdeWizardDeleteSshKeyCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand $command
 */
class IdeWizardDeleteSshKeyCommandTest extends IdeWizardTestBase {

  public function testDelete(): void {
    // Request for IDE data.
    $ideResponse = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/ides/' . IdeHelper::$remoteIdeUuid)->willReturn($ideResponse)->shouldBeCalled();
    $ide = new IdeResponse((object) $ideResponse);
    $mockBody = $this->mockListSshKeysRequestWithIdeKey($ide);

    $this->mockDeleteSshKeyRequest($mockBody->{'_embedded'}->items[0]->uuid);

    // Create the file so it can be deleted.
    $sshKeyFilename = $this->command::getSshKeyFilename(IdeHelper::$remoteIdeUuid);
    $this->fs->touch($this->sshDir . '/' . $sshKeyFilename);
    $this->fs->dumpFile($this->sshDir . '/' . $sshKeyFilename . '.pub', $mockBody->{'_embedded'}->items[0]->public_key);

    // Run it!
    $this->executeCommand([]);

    $this->prophet->checkPredictions();
    $this->assertFileDoesNotExist($this->sshDir . '/' . $sshKeyFilename);
  }

  /**
   * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeWizardDeleteSshKeyCommand::class);
  }

}
