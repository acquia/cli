<?php

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
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
    $applications_response = $this->mockApplicationsRequest();
    $application_response = $applications_response->{'_embedded'}->items[0];
    $local_machine_helper = $this->mockLocalMachineHelper();

    /** @var Filesystem|\Prophecy\Prophecy\ObjectProphecy $file_system */
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $file_system->exists(Argument::type('string'))->willReturn(TRUE);

    /** @var \Symfony\Component\Finder\Finder|\Prophecy\Prophecy\ObjectProphecy $finder */
    $finder = $this->prophet->prophesize(Finder::class);
    $finder->files()->willReturn($finder);
    $finder->in(Argument::type('string'))->willReturn($finder);
    $finder->name(Argument::type('string'))->willReturn($finder);
    $finder->ignoreUnreadableDirs()->willReturn($finder);
    $file = $this->prophet->prophesize(SplFileInfo::class);
    $file_name = 'id_rsa.pub';
    $file->getFileName()->willReturn($file_name);
    $file->getRealPath()->willReturn('somepath');
    $local_machine_helper->readFile('somepath')->willReturn($mock_request_args['public_key']);
    $finder->getIterator()->willReturn(new \ArrayIterator([$file->reveal()]));

    $local_machine_helper->getFinder()->willReturn($finder);

    $this->mockEnvironmentsRequest($application_response);
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $environments_response = $this->getMockEnvironmentsResponse();
    $ssh_helper = $this->mockPollCloudViaSsh($environments_response->_embedded->items[0]);
    $this->command->sshHelper = $ssh_helper->reveal();

    // Choose a local SSH key to upload to the Cloud Platform.
    //$temp_file_name = $this->createLocalSshKey($mock_request_args['public_key']);
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
    $this->assertStringContainsString("Uploaded $file_name to the Cloud Platform with label " . $mock_request_args['label'], $output);
    $this->assertStringContainsString('Waiting for new key to be provisioned on the Cloud Platform...', $output);
    $this->assertStringContainsString('Your SSH key is ready for use!', $output);
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
