<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Class SshKeyCreateCommandTest
 * @property SshKeyCreateCommand $command
 * @package Acquia\Cli\Tests\Ssh
 */
class SshKeyCreateCommandTest extends CommandTestBase {

  protected string $filename = 'id_rsa_acli_test';

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyCreateCommand::class);
  }

  /**
   * @return array[]
   */
  public function providerTestCreate(): array {
    return [
      [
        TRUE,
        // Args.
        [
          '--filename' => $this->filename,
          '--password' => 'acli123'
        ],
        // Inputs.
        []
      ],
      [
        TRUE,
        // Args.
        [],
        // Inputs.
        [
          // Enter a filename for your new local SSH key:
          $this->filename,
          // Enter a password for your SSH key:
          'acli123',
        ]
      ],
      [
        FALSE,
        // Args.
        [],
        // Inputs.
        [
          // Enter a filename for your new local SSH key:
          $this->filename,
          // Enter a password for your SSH key:
          'acli123',
        ]
      ],
    ];
  }

  /**
   * Tests the 'ssh-key:create' command.
   *
   * @dataProvider providerTestCreate
   * @throws \Exception
   */
  public function testCreate($ssh_add_success, $args, $inputs): void {
    $ssh_key_filepath = Path::join($this->sshDir, '/' . $this->filename);
    $this->fs->remove($ssh_key_filepath);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper->getLocalFilepath('~/.passphrase')->willReturn('~/.passphrase');
    /** @var Filesystem|ObjectProphecy $file_system */
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $this->mockAddSshKeyToAgent($local_machine_helper, $file_system);
    $this->mockSshAgentList($local_machine_helper, $ssh_add_success);
    $this->mockGenerateSshKey($local_machine_helper);

    $local_machine_helper->getFilesystem()->willReturn($file_system->reveal())->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->executeCommand($args, $inputs);
  }

}
