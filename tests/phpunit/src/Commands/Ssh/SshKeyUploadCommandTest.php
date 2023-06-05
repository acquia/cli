<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class SshKeyUploadCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(SshKeyUploadCommand::class);
  }

  /**
   * @return array<mixed>
   */
  public function providerTestUpload(): array {
    $sshKeysRequestBody = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    return [
      [
        // Args.
        [],
        // Inputs.
        [
          // Choose key.
          '0',
          // Enter a Cloud Platform label for this SSH key:
          $sshKeysRequestBody['label'],
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
          '--filepath' => 'id_rsa.pub',
          '--label' => $sshKeysRequestBody['label'],
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
   */
  public function testUpload(mixed $args, mixed $inputs, mixed $perms): void {
    $sshKeysRequestBody = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
    $body = [
      'json' => [
        'label' => $sshKeysRequestBody['label'],
        'public_key' => $sshKeysRequestBody['public_key'],
      ],
    ];
    $this->mockRequest('postAccountSshKeys', NULL, $body);
    $this->mockListSshKeyRequestWithUploadedKey($sshKeysRequestBody);
    $applicationsResponse = $this->mockApplicationsRequest();
    $applicationResponse = $this->mockApplicationRequest();
    $this->mockPermissionsRequest($applicationResponse, $perms);

    $localMachineHelper = $this->mockLocalMachineHelper();
    /** @var Filesystem|\Prophecy\Prophecy\ObjectProphecy $fileSystem */
    $fileSystem = $this->prophet->prophesize(Filesystem::class);
    $fileName = $this->mockGetLocalSshKey($localMachineHelper, $fileSystem, $sshKeysRequestBody['public_key']);

    $localMachineHelper->getFilesystem()->willReturn($fileSystem);
    $fileSystem->exists(Argument::type('string'))->willReturn(TRUE);
    $localMachineHelper->getLocalFilepath(Argument::containingString('id_rsa'))->willReturn('id_rsa.pub');
    $localMachineHelper->readFile(Argument::type('string'))->willReturn($sshKeysRequestBody['public_key']);

    $this->command->localMachineHelper = $localMachineHelper->reveal();

    if ($perms) {
      $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
      $sshHelper = $this->mockPollCloudViaSsh($environmentsResponse);
      $this->command->sshHelper = $sshHelper->reveal();
    }

    // Choose a local SSH key to upload to the Cloud Platform.
    $this->executeCommand($args, $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString("Uploaded $fileName to the Cloud Platform with label " . $sshKeysRequestBody['label'], $output);
    $this->assertStringContainsString('Would you like to wait until your key is installed on all of your application\'s servers?', $output);
    $this->assertStringContainsString('Your SSH key is ready for use!', $output);
  }

  public function testInvalidFilepath(): void {
    $inputs = [
      // Choose key.
      '0',
      // Label
      'Test',
    ];
    $filepath = Path::join(sys_get_temp_dir(), 'notarealfile');
    $args = ['--filepath' => $filepath];
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage("The filepath $filepath is not valid");
    $this->executeCommand($args, $inputs);
  }

}
