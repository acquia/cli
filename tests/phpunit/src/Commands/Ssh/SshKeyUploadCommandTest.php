<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class SshKeyCreateUploadCommandTest
 * @property SshKeyUploadCommand $command
 * @package Acquia\Cli\Tests\Ssh
 */
class SshKeyUploadCommandTest extends CommandTestBase
{

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyUploadCommand::class);
  }

  /**
   * Tests the 'ssh-key:upload' command.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testUpload(): void {
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $this->mockUploadSshKey();
    $this->mockListSshKeyRequestWithUploadedKey($mock_request_args);
    $application_response = $this->mockApplicationsRequest();
    $this->mockEnvironmentsRequest($application_response);

    // Choose a local SSH key to upload to the Cloud Platform.
    $temp_file_name = $this->createLocalSshKey($mock_request_args['public_key']);
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
    $this->assertStringContainsString('Choose a local SSH key to upload to the Cloud Platform', $output);
    $this->assertStringContainsString('Please enter a Cloud Platform label for this SSH key:', $output);
    $base_filename = basename($temp_file_name);
    $this->assertStringContainsString("Uploaded $base_filename to the Cloud Platform with label " . $mock_request_args['label'], $output);
    $this->assertStringContainsString('Waiting for new key to be provisioned on the Cloud Platform...', $output);
    $this->assertStringContainsString('Your SSH key is ready for use.', $output);
  }

  public function testInvalidFilepath() {
    $inputs = [
      // Choose key.
      '0',
      // Label
      'Test'
    ];
    $filepath = Path::join(sys_get_temp_dir(), 'notarealfile');
    $args = ['--filepath' => $filepath];
    try {
      $this->executeCommand($args, $inputs);
    }
    catch (AcquiaCliException $exception) {
      $this->assertEquals("The filepath $filepath is not valid", $exception->getMessage());
    }
  }

}
