<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\IdeResponse;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class IdeWizardCreateSshKeyCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeWizardCreateSshKeyCommandTest extends IdeWizardTestBase {

  protected $cloudClient;

  protected $ide;

  // Tests:
  // Delete from cloud.
  // Delete private key.
  // Delete public key.
  // Delete from keychain.
  // Delete local passphrase file.
  // Assert added to keychain.
  // Assert both keys exist.
  // Assert uploaded to cloud.
  //
  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->mockApplicationRequest();
    $this->cloudClient = $this->getMockClient();
    $this->mockListSshKeysRequest();
    $this->ide = $this->mockIdeRequest();
  }

  /**
   * Tests the 'ide:wizard:ssh-key:create' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testCreate(): void {
    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $environments_response = $this->getMockResponseFromSpec('/environments/{environmentId}', 'get', '200');
    $this->clientProphecy->request('get', "/applications/{$this->application_uuid}/environments")->willReturn([$environments_response])->shouldBeCalled();

    // List uploaded keys.
    $this->mockUploadSshKey();

    // Poll Cloud.
    $ssh_helper = $this->mockPollCloudViaSsh($environments_response);
    $this->application->setSshHelper($ssh_helper->reveal());

    // Remove SSH key if it exists.
    $ssh_key_filename = $this->command->getSshKeyFilename($this->remote_ide_uuid);
    $this->fs->remove(Path::join(sys_get_temp_dir(), $ssh_key_filename));

    // Set properties and execute.
    $this->command->getApplication()->setSshKeysDir(sys_get_temp_dir());
    $this->executeCommand([], [
      // Would you like to link the project at ... ?
      'y',
    ]);

    // Assertions.
    $this->prophet->checkPredictions();
    $this->assertFileExists($this->command->getApplication()->getSshKeysDir() . '/' . $ssh_key_filename);
    $this->assertFileExists($this->command->getApplication()->getSshKeysDir() . '/' . str_replace('.pub', '', $ssh_key_filename));
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testSshKeyAlreadyUploaded(): void {
    $this->setCommand($this->createCommand());
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $label = $this->command->getIdeSshKeyLabel($this->ide);
    $ssh_keys_response = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    // Make the uploaded key match the created one.
    $ssh_keys_response->_embedded->items[0]->public_key = $mock_request_args['public_key'];
    $this->clientProphecy->request('get', '/account/ssh-keys')
      ->willReturn($ssh_keys_response->{'_embedded'}->items)      
      ->shouldBeCalled();

    $this->cloudClient->request('get', '/account/ssh-keys/' . $ssh_keys_response->_embedded->items[0]->uuid)
      ->willReturn($ssh_keys_response->{'_embedded'}->items[0])
      ->shouldBeCalled();

    $delete_response = $this->prophet->prophesize(ResponseInterface::class);
    $delete_response->getStatusCode()->willReturn(202);
    $this->cloudClient->makeRequest('delete', '/account/ssh-keys/' . $ssh_keys_response->_embedded->items[0]->uuid)
      ->willReturn($delete_response->reveal())
      ->shouldBeCalled();

    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $environments_response = $this->getMockResponseFromSpec('/environments/{environmentId}', 'get', '200');
    $this->cloudClient->request('get', "/applications/{$this->application_uuid}/environments")->willReturn([$environments_response])->shouldBeCalled();

    // List uploaded keys.
    $this->mockUploadSshKey($this->cloudClient);

    // Poll Cloud.
    $ssh_helper = $this->mockPollCloudViaSsh($environments_response);
    $this->application->setSshHelper($ssh_helper->reveal());

    $public_key_file_path = $this->createLocalSshKey($mock_request_args['public_key']);
    $base_filename = basename($public_key_file_path);
    $this->application->setSshKeysDir(sys_get_temp_dir());
    try {
      $this->executeCommand([], []);
    }
    catch (AcquiaCliException $exception) {
      $this->assertEquals('You have already uploaded a local key to Acquia Cloud. You don\'t need to create a new one.', $exception->getMessage());
    }
  }

  /**
   * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
   */
  protected function createCommand(): Command {
    return new IdeWizardCreateSshKeyCommand();
  }

  // @todo Test that this can only be run inside IDE.

  /**
   * @return \AcquiaCloudApi\Response\IdeResponse
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockIdeRequest(): \AcquiaCloudApi\Response\IdeResponse {
    $ide_response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/ides/' . $this->remote_ide_uuid)->willReturn($ide_response)->shouldBeCalled();
    $ide = new IdeResponse((object) $ide_response);
    return $ide;
  }

  /**
   * @param object $environments_response
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockPollCloudViaSsh($environments_response): \Prophecy\Prophecy\ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $ssh_helper = $this->prophet->prophesize(SshHelper::class);
    $ssh_helper->executeCommand(new EnvironmentResponse($environments_response), ['ls'], FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    return $ssh_helper;
  }

}
