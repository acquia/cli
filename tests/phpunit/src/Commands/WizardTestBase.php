<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class WizardTestBase.
 */
abstract class WizardTestBase extends CommandTestBase {

  /**
   * @var string
   */
  public static $application_uuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

  /**
   * @var string
   */
  protected $sshKeyFileName;

  /**
   * This method is called before each test.
   *
   * @param null $output
   *
   */
  public function setUp($output = NULL): void {
    TestBase::setEnvVars(self::getEnvVars());
    parent::setUp();
    $this->getCommandTester();
    $this->application->addCommands([
      $this->injectCommand(SshKeyCreateCommand::class),
      $this->injectCommand(SshKeyDeleteCommand::class),
      $this->injectCommand(SshKeyUploadCommand::class),
    ]);
  }

  protected function tearDown(): void {
    parent::tearDown();
    TestBase::unsetEnvVars(self::getEnvVars());
  }

  public static function getEnvVars(): array {
    return [
      'ACQUIA_APPLICATION_UUID' => self::$application_uuid,
    ];
  }

  /**
   * Tests the 'gitlab:wizard:ssh-key:create' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function runTestCreate(): void {
    $environments_response = $this->getMockEnvironmentsResponse();
    $selected_environment = $environments_response->_embedded->items[0];
    $this->clientProphecy->request('get', "/applications/{$this::$application_uuid}/environments")->willReturn($environments_response->_embedded->items)->shouldBeCalled();

    // List uploaded keys.
    $this->mockUploadSshKey();

    $local_machine_helper = $this->mockLocalMachineHelper();

    // Poll Cloud.
    $ssh_helper = $this->mockPollCloudViaSsh($selected_environment);
    $this->command->sshHelper = $ssh_helper->reveal();

    $this->mockGenerateSshKey($local_machine_helper);
    $this->mockSshAgentList($local_machine_helper);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->application->find(SshKeyCreateCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;
    $this->application->find(SshKeyUploadCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;
    $this->application->find(SshKeyDeleteCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;

    // Remove SSH key if it exists.
    $this->fs->remove(Path::join(sys_get_temp_dir(), $this->sshKeyFileName));

    // Set properties and execute.
    $this->executeCommand([], [
      // Would you like to link the project at ... ?
      'y',
    ]);

    // Assertions.
    $this->prophet->checkPredictions();
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function runTestSshKeyAlreadyUploaded(): void {
    $mock_request_args = $this->getMockRequestBodyFromSpec('/account/ssh-keys');
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

    $local_machine_helper = $this->mockLocalMachineHelper();

    // List uploaded keys.
    $this->mockUploadSshKey();

    // Poll Cloud.
    $ssh_helper = $this->mockPollCloudViaSsh($selected_environment);
    $this->command->sshHelper = $ssh_helper->reveal();

    $this->mockGenerateSshKey($local_machine_helper);
    $this->mockSshAgentList($local_machine_helper);
    $process = $this->mockProcess();
    $local_machine_helper->executeFromCmd(Argument::type('string'), NULL, NULL, FALSE)
      ->shouldBeCalled()
      ->willReturn($process->reveal());
    $local_machine_helper->writeFile(Argument::type('string'), Argument::type('string'))
      ->shouldBeCalled();

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->application->find(SshKeyCreateCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;
    $this->application->find(SshKeyUploadCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;
    $this->application->find(SshKeyDeleteCommand::getDefaultName())->localMachineHelper = $this->command->localMachineHelper;

    $this->createLocalSshKey($mock_request_args['public_key']);
    try {
      $this->executeCommand([], []);
    }
    catch (AcquiaCliException $exception) {
      $this->assertEquals('You have already uploaded a local key to the Cloud Platform. You don\'t need to create a new one.', $exception->getMessage());
    }
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

  protected function mockSshAgentList($local_machine_helper): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $process->getOutput()->willReturn('thekey!');
    $local_machine_helper->getLocalFilepath('~/.passphrase')
      ->willReturn('/tmp/.passphrase');
    $local_machine_helper->execute([
      'ssh-add',
      '-L',
    ], NULL, NULL, FALSE)->shouldBeCalled()->willReturn($process->reveal());
  }

}
