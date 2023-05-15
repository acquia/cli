<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property SshKeyListCommand $command
 */
class SshKeyListCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyListCommand::class);
  }

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->setupFsFixture();
    $this->command = $this->createCommand();
  }

  /**
   * Tests the 'ssh-key:list' command.
   */
  public function testList(): void {

    $mockBody = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $this->clientProphecy->request('get', '/account/ssh-keys')->willReturn($mockBody->{'_embedded'}->items)->shouldBeCalled();
    $mockRequestArgs = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $tempFileName = $this->createLocalSshKey($mockRequestArgs['public_key']);
    $baseFilename = basename($tempFileName);
    $this->executeCommand();

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Local filename', $output);
    $this->assertStringContainsString('Cloud Platform label', $output);
    $this->assertStringContainsString('Fingerprint', $output);
    $this->assertStringContainsString($baseFilename, $output);
    $this->assertStringContainsString($mockBody->_embedded->items[0]->label, $output);
    $this->assertStringContainsString($mockBody->_embedded->items[1]->label, $output);
    $this->assertStringContainsString($mockBody->_embedded->items[2]->label, $output);
  }

}
