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

    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $this->clientProphecy->request('get', '/account/ssh-keys')->willReturn($mock_body->{'_embedded'}->items)->shouldBeCalled();
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $temp_file_name = $this->createLocalSshKey($mock_request_args['public_key']);
    $base_filename = basename($temp_file_name);
    $this->executeCommand();

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Local filename', $output);
    $this->assertStringContainsString('Cloud Platform label', $output);
    $this->assertStringContainsString('Fingerprint', $output);
    $this->assertStringContainsString($base_filename, $output);
    $this->assertStringContainsString($mock_body->_embedded->items[0]->label, $output);
    $this->assertStringContainsString($mock_body->_embedded->items[1]->label, $output);
    $this->assertStringContainsString($mock_body->_embedded->items[2]->label, $output);
  }

}
