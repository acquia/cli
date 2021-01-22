<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class CommandTestBase.
 * @property \Acquia\Cli\Command\CommandBase $command
 */
abstract class CommandTestBase extends TestBase {

  /**
   * The command tester.
   *
   * @var \Symfony\Component\Console\Tester\CommandTester
   */
  private $commandTester;

  /**
   * @var \Acquia\Cli\Command\CommandBase
   */
  protected $command;

  /**
   * Creates a command object to test.
   *
   * @return \Symfony\Component\Console\Command\Command
   *   A command object with mocked dependencies injected.
   */
  abstract protected function createCommand(): Command;

  /**
   * This method is called before each test.
   *
   * @param OutputInterface $output
   */
  protected function setUp($output = NULL): void {
    parent::setUp();
    if (!isset($this->command)) {
      $this->command = $this->createCommand();
    }
    // Mock guzzle requests for update checks so we don't actually hit Github.
    /** @var \Prophecy\Prophecy\ObjectProphecy|\GuzzleHttp\Psr7\Response $guzzle_response */
    $guzzle_response = $this->prophet->prophesize(Response::class);
    $guzzle_response->getBody()->willReturn();
    $guzzle_client = $this->prophet->prophesize(Client::class);
    $guzzle_client->get('https://api.github.com/repos/acquia/cli/releases')->willReturn($guzzle_response->reveal());
    $this->command->setUpdateClient($guzzle_client->reveal());
    $this->printTestName();
  }

  protected function setCommand(Command $command): void {
    $this->command = $command;
  }

  /**
   * Executes a given command with the command tester.
   *
   * @param array $args
   *   The command arguments.
   * @param string[] $inputs
   *   An array of strings representing each input passed to the command input
   *   stream.
   *
   * @throws \Exception
   */
  protected function executeCommand(array $args = [], array $inputs = []): void {
    $cwd = $this->projectFixtureDir;
    chdir($cwd);
    $tester = $this->getCommandTester();
    $tester->setInputs($inputs);
    $command_name = $this->command->getName();
    $args = array_merge(['command' => $command_name], $args);

    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln('');
      $this->consoleOutput->writeln('Executing <comment>' . $this->command->getName() . '</comment> in ' . $cwd);
      $this->consoleOutput->writeln('<comment>------Begin command output-------</comment>');
    }

    try {
      $tester->execute($args, ['verbosity' => Output::VERBOSITY_VERY_VERBOSE]);
    }
    catch (Exception $e) {if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
        print $this->getDisplay();
      }
      throw $e;
    }

    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln($tester->getDisplay());
      $this->consoleOutput->writeln('<comment>------End command output---------</comment>');
      $this->consoleOutput->writeln('');
    }
  }

  /**
   * Gets the command tester.
   *
   * @return \Symfony\Component\Console\Tester\CommandTester
   *   A command tester.
   */
  protected function getCommandTester(): CommandTester {
    if ($this->commandTester) {
      return $this->commandTester;
    }

    $this->application->add($this->command);
    $found_command = $this->application->find($this->command->getName());
    $this->assertInstanceOf(get_class($this->command), $found_command, 'Instantiated class.');
    $this->commandTester = new CommandTester($found_command);

    return $this->commandTester;
  }

  /**
   * Gets the display returned by the last execution of the command.
   *
   * @return string
   *   The display.
   */
  protected function getDisplay(): string {
    return $this->getCommandTester()->getDisplay();
  }

  /**
   * Gets the status code returned by the last execution of the command.
   *
   * @return int
   *   The status code.
   */
  protected function getStatusCode(): int {
    return $this->getCommandTester()->getStatusCode();
  }

  /**
   * Write full width line.
   *
   * @param string $message
   *   Message.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output.
   */
  protected function writeFullWidthLine($message, OutputInterface $output): void {
    $terminal_width = (new Terminal())->getWidth();
    $padding_len = ($terminal_width - strlen($message)) / 2;
    $pad = $padding_len > 0 ? str_repeat('-', $padding_len) : '';
    $output->writeln("<comment>{$pad}{$message}{$pad}</comment>");
  }

  /**
   * Prints the name of the PHPUnit test to output.
   */
  protected function printTestName(): void {
    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln("");
      $this->writeFullWidthLine(get_class($this) . "::" . $this->getName(), $this->consoleOutput);
    }
  }

  protected function getTargetGitConfigFixture() {
    return Path::join($this->fixtureDir, 'project', '.git', 'config');
  }

  protected function getSourceGitConfigFixture() {
    return Path::join($this->fixtureDir, 'git_config');
  }

  /**
   * Creates a mock .git/config.
   */
  protected function createMockGitConfigFile(): void {
    // Create mock git config file.
    $this->fs->remove([$this->getTargetGitConfigFixture()]);
    $this->fs->copy($this->getSourceGitConfigFixture(), $this->getTargetGitConfigFixture());
  }

  /**
   * Remove mock .git/config.
   */
  protected function removeMockGitConfig(): void {
    $this->fs->remove([$this->getTargetGitConfigFixture(), dirname($this->getTargetGitConfigFixture())]);
  }

  /**
   * Create a mock LocalMachineHelper.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockLocalMachineHelper(): ObjectProphecy {
    $local_machine_helper = $this->prophet->prophesize(LocalMachineHelper::class);
    $local_machine_helper->setLogger(Argument::type(ConsoleLogger::class))->shouldBeCalled();
    $local_machine_helper->useTty()->willReturn(FALSE);
    $local_machine_helper->getLocalFilepath(Path::join($this->dataDir, 'acquia-cli.json'))->willReturn(Path::join($this->dataDir, 'acquia-cli.json'));

    return $local_machine_helper;
  }

  /**
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockSshHelper(): \Prophecy\Prophecy\ObjectProphecy {
    $ssh_helper = $this->prophet->prophesize(SshHelper::class);
    $ssh_helper->setLogger(Argument::type(ConsoleLogger::class))->shouldBeCalled();
    return $ssh_helper;
  }

  /**
   * @param object $applications_response
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function mockAcsfEnvironmentsRequest(
    $applications_response
  ) {
    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $response = $this->getMockEnvironmentResponse();
    $acsf_env_response = $this->getAcsfEnvResponse();
    $response->sshUrl = $acsf_env_response->sshUrl;
    $response->ssh_url = $acsf_env_response->sshUrl;
    $response->domains = $acsf_env_response->domains;
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$response])
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @param object $applications_response
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function mockCloudEnvironmentsRequest(
    $applications_response
  ) {
    $response = $this->getMockEnvironmentResponse();
    $cloud_env_response = $this->getCloudEnvResponse();
    $response->sshUrl = $cloud_env_response->ssh_url;
    $response->ssh_url = $cloud_env_response->ssh_url;
    $response->domains = $cloud_env_response->domains;
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$response])
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @return object
   */
  protected function getAcsfEnvResponse() {
    return json_decode(file_get_contents(Path::join($this->fixtureDir, 'acsf_env_response.json')));
  }

  /**
   * @return object
   */
  protected function getCloudEnvResponse() {
    return json_decode(file_get_contents(Path::join($this->fixtureDir, 'cloud_env_response.json')));
  }

  /**
   * @param $ssh_helper
   *
   * @return void
   */
  protected function mockGetAcsfSites($ssh_helper) {
    $acsf_multisite_fetch_process = $this->mockProcess();
    $acsf_multisite_fetch_process->getOutput()->willReturn(file_get_contents(Path::join($this->fixtureDir,
      '/multisite-config.json')))->shouldBeCalled();
    $ssh_helper->executeCommand(
      Argument::type('object'),
      ['cat', '/var/www/site-php/profserv2.dev/multisite-config.json'],
      FALSE
    )->willReturn($acsf_multisite_fetch_process->reveal())->shouldBeCalled();
  }

  /**
   * @param $ssh_helper
   *
   * @return void
   */
  protected function mockGetCloudSites($ssh_helper) {
    $cloud_multisite_fetch_process = $this->mockProcess();
    $cloud_multisite_fetch_process->getOutput()->willReturn("\nbar\ndefault\nfoo\n")->shouldBeCalled();
    $ssh_helper->executeCommand(
      Argument::type('object'),
      ['ls', '/mnt/files/something.dev/sites'],
      FALSE
    )->willReturn($cloud_multisite_fetch_process->reveal())->shouldBeCalled();
  }

  /**
   * @param bool $success
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockProcess($success = TRUE): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn($success ? 0 : 1);
    return $process;
  }

  /**
   * @param object $environments_response
   *
   * @return object
   */
  protected function mockDatabasesResponse(
    $environments_response
  ) {
    $databases_response = json_decode(file_get_contents(Path::join($this->fixtureDir, '/acsf_db_response.json')));
    $this->clientProphecy->request('get',
      "/environments/{$environments_response->id}/databases")
      ->willReturn($databases_response)
      ->shouldBeCalled();

    return $databases_response;
  }

  /**
   * @param object $environments_response
   *
   * @return object
   */
  protected function mockDatabaseBackupsResponse(
    $environments_response,
    $db_name
  ) {
    $databases_response = json_decode(file_get_contents(Path::join($this->fixtureDir, '/backups_response.json')));
    $this->clientProphecy->request('get',
      "/environments/{$environments_response->id}/databases/{$db_name}/backups")
      ->willReturn($databases_response)
      ->shouldBeCalled();

    return $databases_response;
  }

  protected function mockDownloadBackupResponse(
    $environments_response,
    $db_name,
    $backup_id
  ) {
    $stream = $this->prophet->prophesize(StreamInterface::class);
    $this->clientProphecy->request('get',
      "/environments/{$environments_response->id}/databases/{$db_name}/backups/{$backup_id}/actions/download")
      ->willReturn($stream->reveal())
      ->shouldBeCalled();
  }

}
