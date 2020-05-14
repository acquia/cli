<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class SshKeyListCommandTest
 * @property SshKeyListCommand $command
 * @package Acquia\Cli\Tests\Ssh
 */
class SshKeyListCommandTest extends CommandTestBase
{

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new SshKeyListCommand();
  }

  /**
   * Tests the 'ssh-key:upload' command.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testUpload(): void {
    $this->setCommand($this->createCommand());

    $cloud_client = $this->getMockClient();
    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $cloud_client->request('get', '/account/ssh-keys')->willReturn($mock_body->{'_embedded'}->items)->shouldBeCalled();
    $this->application->setAcquiaCloudClient($cloud_client->reveal());
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $temp_file_name = $this->createLocalSshKey($mock_request_args['public_key']);
    $this->application->setSshKeysDir(sys_get_temp_dir());
    $base_filename = basename($temp_file_name);
    $this->executeCommand();

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Local Key Filename', $output);
    $this->assertStringContainsString('Acquia Cloud Key Label', $output);
    $this->assertStringContainsString($base_filename, $output);
    $this->assertStringContainsString($mock_body->_embedded->items[0]->label, $output);
    $this->assertStringContainsString($mock_body->_embedded->items[1]->label, $output);
    $this->assertStringContainsString($mock_body->_embedded->items[2]->label, $output);
  }

}
