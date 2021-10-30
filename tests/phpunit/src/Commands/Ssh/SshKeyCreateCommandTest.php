<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

/**
 * Class SshKeyCreateCommandTest
 * @property SshKeyCreateCommand $command
 * @package Acquia\Cli\Tests\Ssh
 */
class SshKeyCreateCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyCreateCommand::class);
  }

  /**
   * Tests the 'ssh-key:create' command.
   */
  public function testCreate(): void {
    $ssh_key_filename = 'id_rsa_acli_test';
    $ssh_key_filepath = Path::join($this->sshDir, '/' . $ssh_key_filename);
    $this->fs->remove($ssh_key_filepath);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper->getLocalFilepath('~/.passphrase')->willReturn('~/.passphrase');
    /** @var Filesystem|ObjectProphecy $file_system */
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $this->mockAddSshKeyToAgent($local_machine_helper, $file_system);
    $this->mockSshAgentList($local_machine_helper);
    $this->mockGenerateSshKey($local_machine_helper, $file_system);
    $local_machine_helper->getFilesystem()->willReturn($file_system->reveal())->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $inputs = [
        // Please enter a filename for your new local SSH key:
      $ssh_key_filename,
          // Enter a password for your SSH key:
      'acli123',
    ];
    $this->executeCommand([], $inputs);
  }

}
