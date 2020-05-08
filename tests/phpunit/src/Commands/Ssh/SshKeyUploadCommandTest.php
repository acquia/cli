<?php

namespace Acquia\Ads\Tests\Commands\Ssh;

use Acquia\Ads\AcquiaCliApplication;
use Acquia\Ads\Command\Ssh\SshKeyUploadCommand;
use Acquia\Ads\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Class SshKeyCreateUploadCommandTest
 * @property SshKeyUploadCommand $command
 * @package Acquia\Ads\Tests\Ssh
 */
class SshKeyUploadCommandTest extends CommandTestBase
{

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new SshKeyUploadCommand();
  }

  /**
   * Tests the 'ssh-key:upload' command.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testUpload(): void {
    $this->setCommand($this->createCommand());

    /** @var \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client */
    $cloud_client = $this->prophet->prophesize(Client::class);
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $options = [
      'form_params' => $mock_request_args
    ];

    $response = $this->prophet->prophesize(ResponseInterface::class);
    $response->getStatusCode()->willReturn(202);
    $cloud_client->makeRequest('post', '/account/ssh-keys', $options)->willReturn($response->reveal())->shouldBeCalled();

    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $mock_body->_embedded->items[3] = (object) $mock_request_args;
    $cloud_client->request('get', '/account/ssh-keys')->willReturn($mock_body->{'_embedded'}->items)->shouldBeCalled();
    AcquiaCliApplication::setAcquiaCloudClient($cloud_client->reveal());

    // Choose a local SSH key to upload to Acquia Cloud.
    $temp_file_name = $this->createLocalSshKey($mock_request_args['public_key']);
    $this->command->getApplication()->setSshKeysDir(sys_get_temp_dir());
    $inputs = [
      // Choose key.
      '0',
      // Label
      $mock_request_args['label'],
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Choose a local SSH key to upload to Acquia Cloud:', $output);
    $this->assertStringContainsString('Please enter a Acquia Cloud label for this SSH key:', $output);
    $base_filename = basename($temp_file_name);
    $this->assertStringContainsString("Uploaded $base_filename to Acquia Cloud with label " . $mock_request_args['label'], $output);
    $this->assertStringContainsString('Waiting for new key to be provisioned on Acquia Cloud servers...', $output);
    $this->assertStringContainsString('Your SSH key is ready for use.', $output);
  }

}
