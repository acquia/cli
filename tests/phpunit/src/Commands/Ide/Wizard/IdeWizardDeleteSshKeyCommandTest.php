<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\Wizard\IdeWizardDeleteSshKeyCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;

/**
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand $command
 */
class IdeWizardDeleteSshKeyCommandTest extends IdeWizardTestBase {

  public function testDelete(): void {
    $mockBody = $this->mockListSshKeysRequestWithIdeKey(IdeHelper::$remoteIdeLabel, IdeHelper::$remoteIdeUuid);

    $this->mockDeleteSshKeyRequest($mockBody->{'_embedded'}->items[0]->uuid);

    // Create the file so it can be deleted.
    $sshKeyFilename = $this->command::getSshKeyFilename(IdeHelper::$remoteIdeUuid);
    $this->fs->touch($this->sshDir . '/' . $sshKeyFilename);
    $this->fs->dumpFile($this->sshDir . '/' . $sshKeyFilename . '.pub', $mockBody->{'_embedded'}->items[0]->public_key);

    // Run it!
    $this->executeCommand();

    $this->assertFileDoesNotExist($this->sshDir . '/' . $sshKeyFilename);
  }

  /**
   * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
   */
  protected function createCommand(): CommandBase {
    return $this->injectCommand(IdeWizardDeleteSshKeyCommand::class);
  }

}
