<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Command\Api\ApiCommandFactory;
use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\CommandFactoryInterface;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Exception;
use Gitlab\Api\Projects;
use Gitlab\Api\Users;
use Gitlab\Exception\RuntimeException;
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
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

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

  protected string $apiCommandPrefix = 'api';

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
    $padding_len = (int) (($terminal_width - strlen($message)) / 2);
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
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockGetEnvironments(): object {
    $environment_response = $this->getMockEnvironmentResponse();
    $this->clientProphecy->request('get',
      "/environments/" . $environment_response->id)
      ->willReturn($environment_response)
      ->shouldBeCalled();
    return $environment_response;
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
    $process->isSuccessful()->willReturn($success);
    $process->getExitCode()->willReturn($success ? 0 : 1);
    if (!$success) {
      $process->getErrorOutput()->willReturn('error');
    }
    else {
      $process->getErrorOutput()->willReturn('');
    }
    return $process;
  }

  /**
   * @param object $environments_response
   *
   * @return array
   */
  protected function mockAcsfDatabasesResponse(
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
    $stream = $this->prophet->prophesize(StreamInterface::class);
    $stream->__toString()->willReturn('');
    $guzzle_response->getBody()->willReturn($stream->reveal());
    $guzzle_response->getReasonPhrase()->willReturn('');
    $guzzle_response->getStatusCode()->willReturn($status_code);
    $guzzle_client = $this->prophet->prophesize(Client::class);
    $guzzle_client->get('https://api.github.com/repos/acquia/cli/releases')
      ->willReturn($guzzle_response->reveal());
    $this->command->setUpdateClient($guzzle_client->reveal());
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
    $gitProcess = $this->prophet->prophesize(Process::class);
    $gitProcess->isSuccessful()->willReturn(TRUE);
    $gitProcess->getExitCode()->willReturn(128);
    $ssh_helper = $this->mockSshHelper();
    // Mock Git.
    $url_parts = explode(':', $environments_response->_embedded->items[0]->vcs->url);
    $ssh_helper->executeCommand($url_parts[0], ['ls'], FALSE)
      ->willReturn($gitProcess->reveal())
      ->shouldBeCalled();
    // Mock non-prod.
    $ssh_helper->executeCommand(new EnvironmentResponse($environments_response->_embedded->items[0]), ['ls'], FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    // Mock prod.
    $ssh_helper->executeCommand(new EnvironmentResponse($environments_response->_embedded->items[1]), ['ls'], FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    return $ssh_helper;
  }

  /**
   * @param object $environment_response
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockPollCloudGitViaSsh($environment_response): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(128);
    $ssh_helper = $this->mockSshHelper();
    $ssh_helper->executeCommand($environment_response->vcs->url, ['ls'], FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    return $ssh_helper;
  }

  /**
   * @param $local_machine_helper
   * @param $public_key
   *
   * @return string
   */
  protected function mockGetLocalSshKey($local_machine_helper, $file_system, $public_key): string {
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
    $local_machine_helper->readFile('somepath')->willReturn($public_key);
    $finder->getIterator()->willReturn(new \ArrayIterator([$file->reveal()]));
    $local_machine_helper->getFinder()->willReturn($finder);

    return $file_name;
  }

  /**
   * @return \Acquia\Cli\Command\Api\ApiCommandFactory
   */
  protected function getCommandFactory(): CommandFactoryInterface {
    return new ApiCommandFactory(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->projectFixtureDir,
      $this->clientServiceProphecy->reveal(),
      $this->logStreamManagerProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir,
      $this->logger
    );
  }

  /**
   * @return array
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getApiCommands(): array {
    $api_command_helper = new ApiCommandHelper($this->logger);
    $command_factory = $this->getCommandFactory();
    return $api_command_helper->getApiCommands($this->apiSpecFixtureFilePath, $this->apiCommandPrefix, $command_factory);
  }

  /**
   * @param string $name
   *
   * @return \Acquia\Cli\Command\Api\ApiBaseCommand|\Acquia\Cli\Command\Acsf\AcsfCommandBase
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getApiCommandByName(string $name) {
    $api_commands = $this->getApiCommands();
    foreach ($api_commands as $api_command) {
      if ($api_command->getName() === $name) {
        return $api_command;
      }
    }

    return NULL;
  }

  /**
   * @return array
   */
  protected function getMockedGitLabProject($project_id): array {
    return [
      'id' => $project_id,
      'description' => '',
      'name' => 'codestudiodemo',
      'name_with_namespace' => 'Matthew Grasmick / codestudiodemo',
      'path' => 'codestudiodemo',
      'path_with_namespace' => 'matthew.grasmick/codestudiodemo',
      'default_branch' => 'master',
      'topics' =>
        [
          0 => 'Acquia Cloud Application',
        ],
      'http_url_to_repo' => 'https://code.cloudservices.acquia.io/matthew.grasmick/codestudiodemo.git',
      'web_url' => 'https://code.cloudservices.acquia.io/matthew.grasmick/codestudiodemo',
    ];
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy|\Acquia\Cli\Helpers\LocalMachineHelper $local_machine_helper
   *
   * @return \Prophecy\Prophecy\ObjectProphecy|\Gitlab\Client
   */
  protected function mockGitLabAuthenticate(ObjectProphecy|LocalMachineHelper $local_machine_helper, $gitlab_host, $gitlab_token): ObjectProphecy|\Gitlab\Client {
    $this->mockGitlabGetHost($local_machine_helper, $gitlab_host);
    $this->mockGitlabGetToken($local_machine_helper, $gitlab_token);
    $gitlab_client = $this->prophet->prophesize(\Gitlab\Client::class);
    $gitlab_client->users()->willThrow(RuntimeException::class);
    return $gitlab_client;
  }

  /**
   * @param $local_machine_helper
   * @param string $gitlab_token
   * @param string $gitlab_host
   * @param bool $success
   */
  protected function mockGitlabGetToken($local_machine_helper, $gitlab_token, string $gitlab_host, bool $success = TRUE): void {
    $process = $this->mockProcess($success);
    $process->getOutput()->willReturn($gitlab_token);
    $local_machine_helper->execute([
      'glab',
      'config',
      'get',
      'token',
      '--host=' . $gitlab_host
    ], NULL, NULL, FALSE)->willReturn($process->reveal());
  }

  /**
   * @param $local_machine_helper
   * @param string $gitlab_host
   */
  protected function mockGitlabGetHost($local_machine_helper, string $gitlab_host): void {
    $process = $this->mockProcess();
    $process->getOutput()->willReturn($gitlab_host);
    $local_machine_helper->execute([
      'glab',
      'config',
      'get',
      'host'
    ], NULL, NULL, FALSE)->willReturn($process->reveal());
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $gitlab_client
   */
  protected function mockGitLabUsersMe(ObjectProphecy $gitlab_client): void {
    $users = $this->prophet->prophesize(Users::class);
    $me = [
      'id' => 20,
      'username' => 'matthew.grasmick',
      'name' => 'Matthew Grasmick',
      'state' => 'active',
      'avatar_url' => 'https://secure.gravatar.com/avatar/5ee7b8ad954bf7156e6eb57a45d60dec?s=80&d=identicon',
      'web_url' => 'https://code.dev.cloudservices.acquia.io/matthew.grasmick',
      'created_at' => '2021-12-21T02:26:52.240Z',
      'bio' => '',
      'location' => NULL,
      'public_email' => '',
      'skype' => '',
      'linkedin' => '',
      'twitter' => '',
      'website_url' => '',
      'organization' => NULL,
      'job_title' => '',
      'pronouns' => NULL,
      'bot' => FALSE,
      'work_information' => NULL,
      'followers' => 0,
      'following' => 0,
      'local_time' => '2:00 AM',
      'last_sign_in_at' => '2022-01-21T23:00:49.035Z',
      'confirmed_at' => '2021-12-21T02:26:51.898Z',
      'last_activity_on' => '2022-01-22',
      'email' => 'matthew.grasmick@acquia.com',
      'theme_id' => 1,
      'color_scheme_id' => 1,
      'projects_limit' => 100000,
      'current_sign_in_at' => '2022-01-22T01:40:55.418Z',
      'identities' =>
        [],
      'can_create_group' => TRUE,
      'can_create_project' => TRUE,
      'two_factor_enabled' => FALSE,
      'external' => FALSE,
      'private_profile' => FALSE,
      'commit_email' => 'matthew.grasmick@acquia.com',
      'is_admin' => TRUE,
      'note' => '',
    ];
    $users->me()->willReturn($me);
    $gitlab_client->users()->willReturn($users->reveal());
  }

  /**
   * @param $application_uuid
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockGitLabPermissionsRequest($application_uuid) {
    $permissions_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/permissions', 'get', 200);
    $permissions = $permissions_response->_embedded->items;
    $permission = reset($permissions);
    $permission->name = "administer environment variables on non-prod";
    $permissions[] = $permission;
    $this->clientProphecy->request('get', "/applications/{$application_uuid}/permissions")
      ->willReturn($permissions)
      ->shouldBeCalled();
    return $permissions;
  }

  /**
   * @param $application_uuid
   * @param $mocked_gitlab_projects
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockGetGitLabProjects($application_uuid, $gitlab_project_id, $mocked_gitlab_projects): ObjectProphecy {
    $projects = $this->prophet->prophesize(Projects::class);
    $projects->all(['search' => $application_uuid])
      ->willReturn($mocked_gitlab_projects);
    $projects->all()
      ->willReturn([$this->getMockedGitLabProject($gitlab_project_id)]);
    return $projects;
  }

  /**
   * @return array[]
   */
  protected function getMockGitLabVariables(): array {
    return [
      0 =>
        [
          'variable_type' => 'env_var',
          'key' => 'ACQUIA_APPLICATION_UUID',
          'value' => '2b3f7cf0-6602-4590-948b-3b07b1b005ef',
          'protected' => FALSE,
          'masked' => FALSE,
          'environment_scope' => '*',
        ],
      1 =>
        [
          'variable_type' => 'env_var',
          'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
          'value' => '111aae74-e81a-4052-b4b9-a27a62e6b6a6',
          'protected' => FALSE,
          'masked' => FALSE,
          'environment_scope' => '*',
        ],
    ];
  }

}
