<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class SshKeyListCommandTest
 *
 * @property SshKeyListCommand $command
 * @package Acquia\Cli\Tests\Ssh
 */
class SshKeyListCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyListCommand::class);
  }

  /**
   * @throws \JsonException
   * @throws \Exception
   */
  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->setupFsFixture();
    $this->command = $this->createCommand();
  }

  /**
   * Tests the 'ssh-key:list' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Exception
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
