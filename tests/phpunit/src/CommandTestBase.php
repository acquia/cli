<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Command\Command;
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
    $this->setUpdateClient();
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
   * @return ObjectProphecy|LocalMachineHelper
   */
  protected function mockLocalMachineHelper(): ObjectProphecy {
    $local_machine_helper = $this->prophet->prophesize(LocalMachineHelper::class);
    $local_machine_helper->useTty()->willReturn(FALSE);
    $local_machine_helper->getLocalFilepath(Path::join($this->dataDir, 'acquia-cli.json'))->willReturn(Path::join($this->dataDir, 'acquia-cli.json'));

    return $local_machine_helper;
  }

  /**
   * @return ObjectProphecy
   */
  protected function mockSshHelper(): ObjectProphecy {
    $ssh_helper = $this->prophet->prophesize(SshHelper::class);
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
    $environments_response = $this->getMockEnvironmentsResponse();
    foreach ($environments_response->_embedded->items as $environment) {
      $environment->ssh_url = 'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com';
      $environment->domains = ["profserv201dev.enterprise-g1.acquia-sites.com"];
    }
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn($environments_response->_embedded->items)
      ->shouldBeCalled();

    return $environments_response;
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
  protected function mockGetCloudSites($ssh_helper, $environment) {
    $cloud_multisite_fetch_process = $this->mockProcess();
    $cloud_multisite_fetch_process->getOutput()->willReturn("\nbar\ndefault\nfoo\n")->shouldBeCalled();
    $sitegroup = CommandBase::getSiteGroupFromSshUrl($environment->ssh_url);
    $ssh_helper->executeCommand(
      Argument::type('object'),
      ['ls', "/mnt/files/$sitegroup.{$environment->name}/sites"],
      FALSE
    )->willReturn($cloud_multisite_fetch_process->reveal())->shouldBeCalled();
  }

  /**
   * @param bool $success
   *
   * @return ObjectProphecy
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
   * @return array
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
   * @param $db_name
   * @param $backup_id
   *
   * @return object
   */
  protected function mockDatabaseBackupsResponse(
    $environments_response,
    $db_name,
    $backup_id
  ) {
    $database_backups_response = $this->getMockResponseFromSpec('/environments/{environmentId}/databases/{databaseName}/backups', 'get', 200);
    foreach ($database_backups_response->_embedded->items as $backup) {
      $backup->_links->download->href = "/environments/{$environments_response->id}/databases/{$db_name}/backups/{$backup_id}/actions/download";
      $backup->database->name = $db_name;
      // Acquia PHP SDK mutates the property name. Gross workaround, is there a better way?
      $backup->completedAt = $backup->completed_at;
    }
    $this->clientProphecy->request('get',
      "/environments/{$environments_response->id}/databases/{$db_name}/backups")
      ->willReturn($database_backups_response->_embedded->items)
      ->shouldBeCalled();

    return $database_backups_response;
  }

  /**
   * @param $environments_response
   * @param $db_name
   * @param $backup_id
   *
   * @return void
   */
  protected function mockDownloadBackupResponse(
    $environments_response,
    $db_name,
    $backup_id
  ) {
    $stream = $this->prophet->prophesize(StreamInterface::class);
    $this->clientProphecy->stream('get', "/environments/{$environments_response->id}/databases/{$db_name}/backups/{$backup_id}/actions/download")
      ->willReturn($stream->reveal())
      ->shouldBeCalled();
  }

  protected function mockDatabaseBackupCreateResponse(
    $environments_response,
    $db_name
  ) {
    $backup_create_response = $this->getMockResponseFromSpec('/environments/{environmentId}/databases/{databaseName}/backups', 'post', 202)->{'Creating backup'}->value;
    $this->clientProphecy->request('post', "/environments/{$environments_response->id}/databases/{$db_name}/backups")
      ->willReturn($backup_create_response)
      ->shouldBeCalled();

    return $backup_create_response;
  }

  protected function mockNotificationResponse($notification_uuid) {
    $notification_response = $this->getMockResponseFromSpec('/notifications/{notificationUuid}', 'get', 200);
    $this->clientProphecy->request('get', "/notifications/$notification_uuid")
      ->willReturn($notification_response)
      ->shouldBeCalled();

    return $notification_response;
  }

  /**
   * @param ObjectProphecy $local_machine_helper
   */
  protected function mockCreateMySqlDumpOnLocal(ObjectProphecy $local_machine_helper): void {
    $local_machine_helper->checkRequiredBinariesExist(["mysqldump", "gzip"])->shouldBeCalled();
    $process = $this->mockProcess(TRUE);
    $process->getOutput()->willReturn('');
    $command = 'MYSQL_PWD=drupal mysqldump --host=localhost --user=drupal drupal | pv --rate --bytes | gzip -9 > ' . sys_get_temp_dir() . '/acli-mysql-dump-drupal.sql.gz';
    $local_machine_helper->executeFromCmd($command, Argument::type('callable'), NULL, TRUE)->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param ObjectProphecy $local_machine_helper
   */
  protected function mockExecutePvExists(
        ObjectProphecy $local_machine_helper
    ): void {
    $local_machine_helper
            ->commandExists('pv')
            ->willReturn(TRUE)
            ->shouldBeCalled();
  }

  /**
   * Mock guzzle requests for update checks so we don't actually hit Github.
   *
   * @param int $status_code
   */
  protected function setUpdateClient($status_code = 200): void {
    /** @var ObjectProphecy|\GuzzleHttp\Psr7\Response $guzzle_response */
    $guzzle_response = $this->prophet->prophesize(Response::class);
    $guzzle_response->getBody()->willReturn();
    $guzzle_response->getStatusCode()->willReturn($status_code);
    $guzzle_client = $this->prophet->prophesize(Client::class);
    $guzzle_client->get('https://api.github.com/repos/acquia/cli/releases')
      ->willReturn($guzzle_response->reveal());
    $this->command->setUpdateClient($guzzle_client->reveal());
  }

  /**
   * @param object $environment_response
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockPollCloudViaSsh($environment_response): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $ssh_helper = $this->mockSshHelper();
    $ssh_helper->executeCommand(new EnvironmentResponse($environment_response), ['ls'], FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    return $ssh_helper;
  }

}
