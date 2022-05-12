<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class SshKeyCreateUploadCommandTest
 * @property SshKeyUploadCommand $command
 * @package Acquia\Cli\Tests\Ssh
 */
class SshKeyUploadCommandTest extends CommandTestBase
{

  /**
   * @var array
   */
  private $sshKeysRequestBody;

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyUploadCommand::class);
  }

  /**
   * @return array[]
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function providerTestUpload(): array {
    $this->sshKeysRequestBody = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    return [
      [
        // Args.
        [],
        // Inputs.
        [
          // Choose key.
          '0',
          // Please enter a Cloud Platform label for this SSH key:
          $this->sshKeysRequestBody['label'],
          // Would you like to wait until Cloud Platform is ready? (yes/no)
          'y',
          // Would you like Acquia CLI to search for a Cloud application that matches your local git config? (yes/no)
          'y',
        ],
        // Perms.
        TRUE,
      ],
      [
        // Args.
        [
          '--label' => $this->sshKeysRequestBody['label'],
          '--filepath' => 'id_rsa.pub',
        ],
        // Inputs.
        [
          // Would you like to wait until Cloud Platform is ready? (yes/no)
          'y',
          // Would you like Acquia CLI to search for a Cloud application that matches your local git config? (yes/no)
          'y',
        ],
        // Perms.
        FALSE,
      ],
    ];
  }

  /**
   * @dataProvider providerTestUpload
   *
   * Tests the 'ssh-key:upload' command.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testUpload($args, $inputs, $perms): void {
    $this->createMockGitConfigFile();
    $this->sshKeysRequestBody = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $this->mockUploadSshKey();
    $this->mockListSshKeyRequestWithUploadedKey($this->sshKeysRequestBody);
    $applications_response = $this->mockApplicationsRequest();
    $application_response = $this->mockApplicationRequest();
    $this->mockPermissionsRequest($application_response, $perms);

    $local_machine_helper = $this->mockLocalMachineHelper();
    /** @var Filesystem|\Prophecy\Prophecy\ObjectProphecy $file_system */
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $file_name = $this->mockGetLocalSshKey($local_machine_helper, $file_system, $this->sshKeysRequestBody['public_key']);

    $local_machine_helper->getFilesystem()->willReturn($file_system);
    $file_system->exists(Argument::type('string'))->willReturn(TRUE);
    $local_machine_helper->getLocalFilepath(Argument::containingString('id_rsa'))->willReturn('id_rsa.pub');
    $local_machine_helper->readFile(Argument::type('string'))->willReturn($this->sshKeysRequestBody['public_key']);

    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    if ($perms) {
      $ssh_helper = $this->mockPollCloudViaSsh($environments_response);
      $this->command->sshHelper = $ssh_helper->reveal();
    }

    // Choose a local SSH key to upload to the Cloud Platform.
    $this->executeCommand($args, $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString("Uploaded $file_name to the Cloud Platform with label " . $this->sshKeysRequestBody['label'], $output);
    $this->assertStringContainsString('Would you like to wait until Cloud Platform is ready?', $output);
    $this->assertStringContainsString('Your SSH key is ready for use!', $output);
  }

  /**
   * @throws \Exception
   */
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
