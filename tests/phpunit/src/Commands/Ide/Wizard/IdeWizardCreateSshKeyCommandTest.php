<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\IdeResponse;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class IdeWizardCreateSshKeyCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand $command
 * @package Acquia\Cli\Tests\Ide
 *
 * The IdeWizardCreateSshKeyCommand command is designed to thrown an exception if it
 * is executed from a non Cloud Platform IDE environment. Therefore we do not test Windows
 * compatibility. It should only ever be run in a Linux environment.
 *
 * @requires OS linux|darwin
 */
class IdeWizardCreateSshKeyCommandTest extends IdeWizardTestBase {

  protected $ide;

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->mockApplicationRequest();
    $this->mockListSshKeysRequest();
    $this->ide = $this->mockIdeRequest();
  }

  /**
   * Tests the 'ide:wizard:ssh-key:create' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testCreate(): void {
    $environments_response = $this->getMockEnvironmentsResponse();
    $selected_environment = $environments_response->_embedded->items[0];
    $this->clientProphecy->request('get', "/applications/{$this::$application_uuid}/environments")->willReturn($environments_response->_embedded->items)->shouldBeCalled();

    // List uploaded keys.
    $this->mockUploadSshKey();

    // Poll Cloud.
    $ssh_helper = $this->mockPollCloudViaSsh($selected_environment);
    $this->command->sshHelper = $ssh_helper->reveal();

    $this->mockSshAgent();

    // Remove SSH key if it exists.
    $ssh_key_filename = $this->command->getSshKeyFilename($this::$remote_ide_uuid);
    $this->fs->remove(Path::join(sys_get_temp_dir(), $ssh_key_filename));

    // Set properties and execute.
    $this->executeCommand([], [
      // Would you like to link the project at ... ?
      'y',
    ]);

    // Assertions.
    $this->prophet->checkPredictions();
    $this->assertFileExists($this->sshDir . '/' . $ssh_key_filename);
    $this->assertFileExists($this->sshDir . '/' . str_replace('.pub', '', $ssh_key_filename));
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testSshKeyAlreadyUploaded(): void {
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $this->command->getIdeSshKeyLabel($this->ide);
    $ssh_keys_response = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    // Make the uploaded key match the created one.
    $ssh_keys_response->_embedded->items[0]->public_key = $mock_request_args['public_key'];
    $this->clientProphecy->request('get', '/account/ssh-keys')
      ->willReturn($ssh_keys_response->{'_embedded'}->items)
      ->shouldBeCalled();

    $this->clientProphecy->request('get', '/account/ssh-keys/' . $ssh_keys_response->_embedded->items[0]->uuid)
      ->willReturn($ssh_keys_response->{'_embedded'}->items[0])
      ->shouldBeCalled();

    $delete_response = $this->prophet->prophesize(ResponseInterface::class);
    $delete_response->getStatusCode()->willReturn(202);
    $this->clientProphecy->makeRequest('delete', '/account/ssh-keys/' . $ssh_keys_response->_embedded->items[0]->uuid)
      ->willReturn($delete_response->reveal())
      ->shouldBeCalled();

    $environments_response = $this->getMockEnvironmentsResponse();
    $selected_environment = $environments_response->_embedded->items[0];
    $this->clientProphecy->request('get', "/applications/{$this::$application_uuid}/environments")->willReturn($environments_response->_embedded->items)->shouldBeCalled();

    // List uploaded keys.
    $this->mockUploadSshKey();

    // Poll Cloud.
    $ssh_helper = $this->mockPollCloudViaSsh($selected_environment);
    $this->command->sshHelper = $ssh_helper->reveal();

    $this->mockSshAgent();

    $this->createLocalSshKey($mock_request_args['public_key']);
    try {
      $this->executeCommand([], []);
    }
    catch (AcquiaCliException $exception) {
      $this->assertEquals('You have already uploaded a local key to the Cloud Platform. You don\'t need to create a new one.', $exception->getMessage());
    }
  }

  /**
   * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeWizardCreateSshKeyCommand::class);
  }

  // @todo Test that this can only be run inside IDE.

  /**
   * @return \AcquiaCloudApi\Response\IdeResponse
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockIdeRequest(): IdeResponse {
    $ide_response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/ides/' . $this::$remote_ide_uuid)->willReturn($ide_response)->shouldBeCalled();
    $ide = new IdeResponse((object) $ide_response);
    return $ide;
  }

  /**
   * @param object $environments_response
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockPollCloudViaSsh($environments_response): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $ssh_helper = $this->mockSshHelper();
    $ssh_helper->executeCommand(new EnvironmentResponse($environments_response), ['ls'], FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    return $ssh_helper;
  }

  protected function mockSshAgent(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $process->getOutput()->willReturn(NULL);
    $local_machine_helper->executeFromCmd(Argument::type('string'), NULL, NULL, FALSE)
      ->shouldBeCalled()
      ->willReturn($process->reveal());
    $local_machine_helper->getLocalFilepath('~/.passphrase')
      ->willReturn('/tmp/.passphrase');
    $local_machine_helper->getFilesystem()->willReturn($this->fs);
    $local_machine_helper->execute([
      'ssh-add',
      '-L',
    ], NULL, NULL, FALSE)->shouldBeCalled()->willReturn($process->reveal());
    $local_machine_helper->writeFile(Argument::type('string'), Argument::type('string'))
      ->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();
  }

}
