<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property SshKeyDeleteCommand $command
 */
class SshKeyDeleteCommandTest extends CommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(SshKeyDeleteCommand::class);
  }

  public function testDelete(): void {
    $sshKeyListResponse = $this->mockListSshKeysRequest();
    $this->mockRequest('deleteAccountSshKey', $sshKeyListResponse[self::$INPUT_DEFAULT_CHOICE]->uuid, NULL, 'Removed key');

    $inputs = [
      // Choose key.
      self::$INPUT_DEFAULT_CHOICE,
      // Do you also want to delete the corresponding local key files?
      'n',
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Choose an SSH key to delete from the Cloud Platform', $output);
    $this->assertStringContainsString($sshKeyListResponse[self::$INPUT_DEFAULT_CHOICE]->label, $output);
  }

}
