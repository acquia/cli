<?php

/**
 * @file
 */
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

  public function setUp($output = NULL): void {
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

  /**
   * Tests the 'ssh-key:create-upload' command.
   */
  public function testCreateUpload(): void {
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');

    // Create.
    $ssh_key_filename = 'id_rsa';
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper->getLocalFilepath('~/.passphrase')->willReturn('~/.passphrase');
    /** @var Filesystem|ObjectProphecy $file_system */
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $this->mockAddSshKeyToAgent($local_machine_helper, $file_system);
    $this->mockSshAgentList($local_machine_helper);
    $this->mockGenerateSshKey($local_machine_helper, $mock_request_args['public_key']);

    // Upload.
    $this->mockUploadSshKey();
    //$this->mockListSshKeyRequestWithUploadedKey($mock_request_args);
    //$applications_response = $this->mockApplicationsRequest();
    //$this->mockApplicationRequest();
    $this->mockGetLocalSshKey($local_machine_helper, $file_system, $mock_request_args['public_key']);
    //$this->mockEnvironmentsRequest($applications_response);

    $local_machine_helper->getFilesystem()->willReturn($file_system->reveal())->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->application->find(SshKeyCreateCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;
    $this->application->find(SshKeyUploadCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;

    //$environments_response = $this->getMockEnvironmentsResponse();
    //$ssh_helper = $this->mockPollCloudViaSsh($environments_response->_embedded->items[0]);
    //$this->command->sshHelper = $ssh_helper->reveal();

    $inputs = [
      // Enter a filename for your new local SSH key:
      $ssh_key_filename,
      // Enter a password for your SSH key:
      'acli123',
      // Label
      $mock_request_args['label'],
    ];
    $this->executeCommand(['--no-wait' => ''], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Enter the filename of the SSH key (option --filename) [id_rsa_acquia]:', $output);
    $this->assertStringContainsString('Enter the password for the SSH key (option --password) (input will be hidden):', $output);
    $this->assertStringContainsString('Enter the SSH key label to be used with the Cloud Platform (option --label):', $output);
  }

}
