<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Command\Ssh\SshKeyCreateUploadCommand;
use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @property SshKeyCreateUploadCommand $command
 */
class SshKeyCreateUploadCommandTest extends CommandTestBase {

  public function setUp(mixed $output = NULL): void {
    parent::setUp();

    $this->getCommandTester();
    $this->application->addCommands([
      $this->injectCommand(SshKeyCreateCommand::class),
      $this->injectCommand(SshKeyUploadCommand::class),
    ]);
  }

  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyCreateUploadCommand::class);
  }

  public function testCreateUpload(): void {
    $mockRequestArgs = $this->getMockRequestBodyFromSpec('/account/ssh-keys');

    $sshKeyFilename = 'id_rsa';
    $localMachineHelper = $this->mockLocalMachineHelper();
    $localMachineHelper->getLocalFilepath('~/.passphrase')->willReturn('~/.passphrase');
    $fileSystem = $this->prophet->prophesize(Filesystem::class);
    $this->mockAddSshKeyToAgent($localMachineHelper, $fileSystem);
    $this->mockSshAgentList($localMachineHelper);
    $this->mockGenerateSshKey($localMachineHelper, $mockRequestArgs['public_key']);

    $body = [
      'json' => [
        'label' => $mockRequestArgs['label'],
        'public_key' => $mockRequestArgs['public_key'],
      ],
    ];
    $this->mockRequest('postAccountSshKeys', NULL, $body);
    $this->mockGetLocalSshKey($localMachineHelper, $fileSystem, $mockRequestArgs['public_key']);

    $localMachineHelper->getFilesystem()->willReturn($fileSystem->reveal())->shouldBeCalled();
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->application->find(SshKeyCreateCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;
    $this->application->find(SshKeyUploadCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;

    $inputs = [
      // Enter a filename for your new local SSH key:
      $sshKeyFilename,
      // Enter a password for your SSH key:
      'acli123',
      // Label
      $mockRequestArgs['label'],
    ];
    $this->executeCommand(['--no-wait' => ''], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Enter the filename of the SSH key (option --filename) [id_rsa_acquia]:', $output);
    $this->assertStringContainsString('Enter the password for the SSH key (option --password) (input will be hidden):', $output);
    $this->assertStringContainsString('Enter the SSH key label to be used with the Cloud Platform (option --label):', $output);
  }

}
