<?php

/**
 * @file
 */
namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Command\Ssh\SshKeyCreateUploadCommand;
use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Class SshKeyCreateUploadCommandTest
 * @property SshKeyCreateUploadCommand $command
 * @package Acquia\Cli\Tests\Ssh
 */
class SshKeyCreateUploadCommandTest extends CommandTestBase {

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->setCommand($this->createCommand());
    $this->getCommandTester();
    $this->application->addCommands([
      new SshKeyCreateCommand(),
      new SshKeyUploadCommand(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new SshKeyCreateUploadCommand();
  }

  /**
   * Tests the 'ssh-key:create-upload' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testCreateUpload(): void {
    $this->application->setSshKeysDir(sys_get_temp_dir());
    $ssh_key_filename = 'id_rsa_acli_test';
    $ssh_key_filepath = $this->application->getSshKeysDir() . '/' . $ssh_key_filename;
    $this->fs->remove($ssh_key_filepath);

    $cloud_client = $this->getMockClient();
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $this->mockUploadSshKey($cloud_client, $mock_request_args);
    $this->mockListSshKeyRequestWithUploadedKey($mock_request_args, $cloud_client);

    $inputs = [
      // Please enter a filename for your new local SSH key:
      $ssh_key_filename,
      // Enter a password for your SSH key:
      'acli123',
      // Label
      $mock_request_args['label'],
    ];
    $this->executeCommand(['--no-wait' => ''], $inputs);
    $this->assertFileExists($ssh_key_filepath);
    $this->assertFileExists($ssh_key_filepath . '.pub');
  }

}
