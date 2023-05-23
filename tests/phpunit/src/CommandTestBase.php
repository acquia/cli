<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Command\Acsf\AcsfCommandBase;
use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\Api\ApiCommandFactory;
use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\CommandFactoryInterface;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use AcquiaCloudApi\Response\DatabaseResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Exception;
use Gitlab\Api\Projects;
use Gitlab\Api\Users;
use Gitlab\Exception\RuntimeException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Constraint\StringContains;
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
 * @property \Acquia\Cli\Command\CommandBase $command
 */
abstract class CommandTestBase extends TestBase {

  /**
   * The command tester.
   */
  private CommandTester $commandTester;

  // Select the application / SSH key / etc.
  protected static int $INPUT_DEFAULT_CHOICE = 0;

  protected Command $command;

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
   */
  protected function setUp(OutputInterface $output = NULL): void {
    parent::setUp();
    if (!isset($this->command)) {
      $this->command = $this->createCommand();
    }
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
   */
  protected function executeCommand(array $args = [], array $inputs = []): void {
    $cwd = $this->projectDir;
    $tester = $this->getCommandTester();
    $tester->setInputs($inputs);
    $commandName = $this->command->getName();
    $args = array_merge(['command' => $commandName], $args);

    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln('');
      $this->consoleOutput->writeln('Executing <comment>' . $this->command->getName() . '</comment> in ' . $cwd);
      $this->consoleOutput->writeln('<comment>------Begin command output-------</comment>');
    }

    try {
      $tester->execute($args, ['verbosity' => Output::VERBOSITY_VERY_VERBOSE]);
    }
    catch (Exception $e) {
      if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
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
    if (isset($this->commandTester)) {
      return $this->commandTester;
    }

    $this->application->add($this->command);
    $foundCommand = $this->application->find($this->command->getName());
    $this->assertInstanceOf(get_class($this->command), $foundCommand, 'Instantiated class.');
    $this->commandTester = new CommandTester($foundCommand);

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
  protected function writeFullWidthLine(string $message, OutputInterface $output): void {
    $terminalWidth = (new Terminal())->getWidth();
    $paddingLen = (int) (($terminalWidth - strlen($message)) / 2);
    $pad = $paddingLen > 0 ? str_repeat('-', $paddingLen) : '';
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

  protected function getTargetGitConfigFixture(): string {
    return Path::join($this->projectDir, '.git', 'config');
  }

  protected function getSourceGitConfigFixture(): string {
    return Path::join($this->realFixtureDir, 'git_config');
  }

  /**
   * Creates a mock .git/config.
   */
  protected function createMockGitConfigFile(): void {
    // Create mock git config file.
    $this->fs->copy($this->getSourceGitConfigFixture(), $this->getTargetGitConfigFixture());
  }

  /**
   * Remove mock .git/config.
   */
  protected function removeMockGitConfig(): void {
    $this->fs->remove([$this->getTargetGitConfigFixture(), dirname($this->getTargetGitConfigFixture())]);
  }

  protected function mockReadIdePhpVersion(string $phpVersion = '7.1'): LocalMachineHelper|ObjectProphecy {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $localMachineHelper->getLocalFilepath(Path::join($this->dataDir, 'acquia-cli.json'))->willReturn(Path::join($this->dataDir, 'acquia-cli.json'));
    $localMachineHelper->readFile('/home/ide/configs/php/.version')->willReturn("$phpVersion\n")->shouldBeCalled();

    return $localMachineHelper;
  }

  protected function mockLocalMachineHelper(): LocalMachineHelper|ObjectProphecy {
    $localMachineHelper = $this->prophet->prophesize(LocalMachineHelper::class);
    $localMachineHelper->useTty()->willReturn(FALSE);
    return $localMachineHelper;
  }

  /**
   * @return \Acquia\Cli\Helpers\SshHelper|\Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockSshHelper(): SshHelper|ObjectProphecy {
    return $this->prophet->prophesize(SshHelper::class);
  }

  protected function mockGetEnvironments(): object {
    $environmentResponse = $this->getMockEnvironmentResponse();
    $this->clientProphecy->request('get',
      "/environments/" . $environmentResponse->id)
      ->willReturn($environmentResponse)
      ->shouldBeCalled();
    return $environmentResponse;
  }

  public function mockAcsfEnvironmentsRequest(
    object $applicationsResponse
  ): object {
    $environmentsResponse = $this->getMockEnvironmentsResponse();
    foreach ($environmentsResponse->_embedded->items as $environment) {
      $environment->ssh_url = 'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com';
      $environment->domains = ["profserv201dev.enterprise-g1.acquia-sites.com"];
    }
    $this->clientProphecy->request('get',
      "/applications/{$applicationsResponse->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn($environmentsResponse->_embedded->items)
      ->shouldBeCalled();

    return $environmentsResponse;
  }

  protected function mockGetAcsfSites($sshHelper): array {
    $acsfMultisiteFetchProcess = $this->mockProcess();
    $multisiteConfig = file_get_contents(Path::join($this->realFixtureDir, '/multisite-config.json'));
    $acsfMultisiteFetchProcess->getOutput()->willReturn($multisiteConfig)->shouldBeCalled();
    $sshHelper->executeCommand(
      Argument::type('object'),
      ['cat', '/var/www/site-php/profserv2.dev/multisite-config.json'],
      FALSE
    )->willReturn($acsfMultisiteFetchProcess->reveal())->shouldBeCalled();
    return json_decode($multisiteConfig, TRUE);
  }

  protected function mockGetCloudSites($sshHelper, $environment): void {
    $cloudMultisiteFetchProcess = $this->mockProcess();
    $cloudMultisiteFetchProcess->getOutput()->willReturn("\nbar\ndefault\nfoo\n")->shouldBeCalled();
    $sitegroup = CommandBase::getSiteGroupFromSshUrl($environment->ssh_url);
    $sshHelper->executeCommand(
      Argument::type('object'),
      ['ls', "/mnt/files/$sitegroup.{$environment->name}/sites"],
      FALSE
    )->willReturn($cloudMultisiteFetchProcess->reveal())->shouldBeCalled();
  }

  protected function mockProcess(bool $success = TRUE): Process|ObjectProphecy {
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
   * @return \AcquiaCloudApi\Response\DatabaseResponse[]
   */
  protected function mockAcsfDatabasesResponse(
    object $environmentsResponse
  ): array {
    $databasesResponseJson = json_decode(file_get_contents(Path::join($this->realFixtureDir, '/acsf_db_response.json')), FALSE, 512, JSON_THROW_ON_ERROR);
    $databasesResponse = array_map(
      static function ($databaseResponse) {
        return new DatabaseResponse($databaseResponse);
      },
      $databasesResponseJson
    );
    $this->clientProphecy->request('get',
      "/environments/{$environmentsResponse->id}/databases")
      ->willReturn($databasesResponse)
      ->shouldBeCalled();

    return $databasesResponse;
  }

  protected function mockDatabaseBackupsResponse(
    object $environmentsResponse,
    $dbName,
    $backupId
  ): object {
    $databaseBackupsResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/databases/{databaseName}/backups', 'get', 200);
    foreach ($databaseBackupsResponse->_embedded->items as $backup) {
      $backup->_links->download->href = "/environments/{$environmentsResponse->id}/databases/{$dbName}/backups/{$backupId}/actions/download";
      $backup->database->name = $dbName;
      // Acquia PHP SDK mutates the property name. Gross workaround, is there a better way?
      $backup->completedAt = $backup->completed_at;
    }
    $this->clientProphecy->request('get',
      "/environments/{$environmentsResponse->id}/databases/{$dbName}/backups")
      ->willReturn($databaseBackupsResponse->_embedded->items)
      ->shouldBeCalled();

    return $databaseBackupsResponse;
  }

  protected function mockDownloadBackupResponse(
    $environmentsResponse,
    $dbName,
    $backupId
  ): void {
    $stream = $this->prophet->prophesize(StreamInterface::class);
    $this->clientProphecy->stream('get', "/environments/{$environmentsResponse->id}/databases/{$dbName}/backups/{$backupId}/actions/download", [])
      ->willReturn($stream->reveal())
      ->shouldBeCalled();
  }

  protected function mockDatabaseBackupCreateResponse(
    $environmentsResponse,
    $dbName
  ) {
    $backupCreateResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/databases/{databaseName}/backups', 'post', 202)->{'Creating backup'}->value;
    $this->clientProphecy->request('post', "/environments/$environmentsResponse->id/databases/{$dbName}/backups")
      ->willReturn($backupCreateResponse)
      ->shouldBeCalled();

    return $backupCreateResponse;
  }

  protected function mockNotificationResponseFromObject(object $responseWithNotificationLink) {
    return $this->mockNotificationResponse(substr($responseWithNotificationLink->_links->notification->href, -36));
  }

  protected function mockNotificationResponse(string $notificationUuid, string $status = NULL) {
    $notificationResponse = $this->getMockResponseFromSpec('/notifications/{notificationUuid}', 'get', 200);
    if ($status) {
      $notificationResponse->status = $status;
    }
    $this->clientProphecy->request('get', "/notifications/$notificationUuid")
      ->willReturn($notificationResponse)
      ->shouldBeCalled();

    return $notificationResponse;
  }

  protected function mockCreateMySqlDumpOnLocal(ObjectProphecy $localMachineHelper): void {
    $localMachineHelper->checkRequiredBinariesExist(["mysqldump", "gzip"])->shouldBeCalled();
    $process = $this->mockProcess(TRUE);
    $process->getOutput()->willReturn('');
    $command = 'MYSQL_PWD=drupal mysqldump --host=localhost --user=drupal drupal | pv --rate --bytes | gzip -9 > ' . sys_get_temp_dir() . '/acli-mysql-dump-drupal.sql.gz';
    $localMachineHelper->executeFromCmd($command, Argument::type('callable'), NULL, TRUE)->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  protected function mockExecutePvExists(
        ObjectProphecy $localMachineHelper
    ): void {
    $localMachineHelper
            ->commandExists('pv')
            ->willReturn(TRUE)
            ->shouldBeCalled();
  }

  protected function mockExecuteGlabExists(
    ObjectProphecy $localMachineHelper
  ): void {
    $localMachineHelper
      ->commandExists('glab')
      ->willReturn(TRUE)
      ->shouldBeCalled();
  }

  /**
   * Mock guzzle requests for update checks, so we don't actually hit GitHub.
   */
  protected function setUpdateClient(int $statusCode = 200): void {
    /** @var ObjectProphecy|\GuzzleHttp\Psr7\Response $guzzleResponse */
    $guzzleResponse = $this->prophet->prophesize(Response::class);
    $stream = $this->prophet->prophesize(StreamInterface::class);
    $stream->__toString()->willReturn(file_get_contents(Path::join(__DIR__, '..', '..', 'fixtures', 'github-releases.json')));
    $guzzleResponse->getBody()->willReturn($stream->reveal());
    $guzzleResponse->getReasonPhrase()->willReturn('');
    $guzzleResponse->getStatusCode()->willReturn($statusCode);
    $guzzleClient = $this->prophet->prophesize(Client::class);
    $guzzleClient->get('https://api.github.com/repos/acquia/cli/releases')
      ->willReturn($guzzleResponse->reveal());
    $this->command->setUpdateClient($guzzleClient->reveal());
  }

  protected function mockPollCloudViaSsh(object $environmentsResponse): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $gitProcess = $this->prophet->prophesize(Process::class);
    $gitProcess->isSuccessful()->willReturn(TRUE);
    $gitProcess->getExitCode()->willReturn(128);
    $sshHelper = $this->mockSshHelper();
    // Mock Git.
    $urlParts = explode(':', $environmentsResponse->_embedded->items[0]->vcs->url);
    $sshHelper->executeCommand($urlParts[0], ['ls'], FALSE)
      ->willReturn($gitProcess->reveal())
      ->shouldBeCalled();
    // Mock non-prod.
    $sshHelper->executeCommand(new EnvironmentResponse($environmentsResponse->_embedded->items[0]), ['ls'], FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    // Mock prod.
    $sshHelper->executeCommand(new EnvironmentResponse($environmentsResponse->_embedded->items[1]), ['ls'], FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    return $sshHelper;
  }

  protected function mockPollCloudGitViaSsh(object $environmentResponse): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(128);
    $sshHelper = $this->mockSshHelper();
    $sshHelper->executeCommand($environmentResponse->vcs->url, ['ls'], FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    return $sshHelper;
  }

  protected function mockGetLocalSshKey($localMachineHelper, $fileSystem, $publicKey): string {
    $fileSystem->exists(Argument::type('string'))->willReturn(TRUE);
    /** @var \Symfony\Component\Finder\Finder|\Prophecy\Prophecy\ObjectProphecy $finder */
    $finder = $this->prophet->prophesize(Finder::class);
    $finder->files()->willReturn($finder);
    $finder->in(Argument::type('string'))->willReturn($finder);
    $finder->name(Argument::type('string'))->willReturn($finder);
    $finder->ignoreUnreadableDirs()->willReturn($finder);
    $file = $this->prophet->prophesize(SplFileInfo::class);
    $fileName = 'id_rsa.pub';
    $file->getFileName()->willReturn($fileName);
    $file->getRealPath()->willReturn('somepath');
    $localMachineHelper->readFile('somepath')->willReturn($publicKey);
    $finder->getIterator()->willReturn(new \ArrayIterator([$file->reveal()]));
    $localMachineHelper->getFinder()->willReturn($finder);

    return $fileName;
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
      $this->projectDir,
      $this->clientServiceProphecy->reveal(),
      $this->logStreamManagerProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClientProphecy->reveal()
    );
  }

  /**
   * @return array
   */
  protected function getApiCommands(): array {
    $apiCommandHelper = new ApiCommandHelper($this->logger);
    $commandFactory = $this->getCommandFactory();
    return $apiCommandHelper->getApiCommands($this->apiSpecFixtureFilePath, $this->apiCommandPrefix, $commandFactory);
  }

  protected function getApiCommandByName(string $name): ApiBaseCommand|AcsfCommandBase|null {
    $apiCommands = $this->getApiCommands();
    foreach ($apiCommands as $apiCommand) {
      if ($apiCommand->getName() === $name) {
        return $apiCommand;
      }
    }

    return NULL;
  }

  protected function getMockedGitLabProject($projectId): array {
    return [
      'default_branch' => 'master',
      'description' => '',
      'http_url_to_repo' => 'https://code.cloudservices.acquia.io/matthew.grasmick/codestudiodemo.git',
      'id' => $projectId,
      'name' => 'codestudiodemo',
      'name_with_namespace' => 'Matthew Grasmick / codestudiodemo',
      'path' => 'codestudiodemo',
      'path_with_namespace' => 'matthew.grasmick/codestudiodemo',
      'topics' => [
          0 => 'Acquia Cloud Application',
        ],
      'web_url' => 'https://code.cloudservices.acquia.io/matthew.grasmick/codestudiodemo',
    ];
  }

  /**
   * @return \Prophecy\Prophecy\ObjectProphecy|\Gitlab\Client
   */
  protected function mockGitLabAuthenticate(ObjectProphecy|LocalMachineHelper $localMachineHelper, $gitlabHost, $gitlabToken): ObjectProphecy|\Gitlab\Client {
    $this->mockGitlabGetHost($localMachineHelper, $gitlabHost);
    $this->mockGitlabGetToken($localMachineHelper, $gitlabToken, $gitlabHost);
    $gitlabClient = $this->prophet->prophesize(\Gitlab\Client::class);
    $gitlabClient->users()->willThrow(RuntimeException::class);
    return $gitlabClient;
  }

  protected function mockGitlabGetToken($localMachineHelper, string $gitlabToken, string $gitlabHost, bool $success = TRUE): void {
    $process = $this->mockProcess($success);
    $process->getOutput()->willReturn($gitlabToken);
    $localMachineHelper->execute([
      'glab',
      'config',
      'get',
      'token',
      '--host=' . $gitlabHost,
    ], NULL, NULL, FALSE)->willReturn($process->reveal());
  }

  protected function mockGitlabGetHost($localMachineHelper, string $gitlabHost): void {
    $process = $this->mockProcess();
    $process->getOutput()->willReturn($gitlabHost);
    $localMachineHelper->execute([
      'glab',
      'config',
      'get',
      'host',
    ], NULL, NULL, FALSE)->willReturn($process->reveal());
  }

  protected function mockGitLabUsersMe(ObjectProphecy|\Gitlab\Client $gitlabClient): void {
    $users = $this->prophet->prophesize(Users::class);
    $me = [
      'avatar_url' => 'https://secure.gravatar.com/avatar/5ee7b8ad954bf7156e6eb57a45d60dec?s=80&d=identicon',
      'bio' => '',
      'bot' => FALSE,
      'can_create_group' => TRUE,
      'can_create_project' => TRUE,
      'color_scheme_id' => 1,
      'commit_email' => 'matthew.grasmick@acquia.com',
      'confirmed_at' => '2021-12-21T02:26:51.898Z',
      'created_at' => '2021-12-21T02:26:52.240Z',
      'current_sign_in_at' => '2022-01-22T01:40:55.418Z',
      'email' => 'matthew.grasmick@acquia.com',
      'external' => FALSE,
      'followers' => 0,
      'following' => 0,
      'id' => 20,
      'identities' => [],
      'is_admin' => TRUE,
      'job_title' => '',
      'last_activity_on' => '2022-01-22',
      'last_sign_in_at' => '2022-01-21T23:00:49.035Z',
      'linkedin' => '',
      'local_time' => '2:00 AM',
      'location' => NULL,
      'name' => 'Matthew Grasmick',
      'note' => '',
      'organization' => NULL,
      'private_profile' => FALSE,
      'projects_limit' => 100000,
      'pronouns' => NULL,
      'public_email' => '',
      'skype' => '',
      'state' => 'active',
      'theme_id' => 1,
      'twitter' => '',
      'two_factor_enabled' => FALSE,
      'username' => 'matthew.grasmick',
      'website_url' => '',
      'web_url' => 'https://code.dev.cloudservices.acquia.io/matthew.grasmick',
      'work_information' => NULL,
    ];
    $users->me()->willReturn($me);
    $gitlabClient->users()->willReturn($users->reveal());
  }

  /**
   * @param $applicationUuid
   * @return array
   */
  protected function mockGitLabPermissionsRequest($applicationUuid): array {
    $permissionsResponse = $this->getMockResponseFromSpec('/applications/{applicationUuid}/permissions', 'get', 200);
    $permissions = $permissionsResponse->_embedded->items;
    $permission = clone reset($permissions);
    $permission->name = "administer environment variables on non-prod";
    $permissions[] = $permission;
    $this->clientProphecy->request('get', "/applications/{$applicationUuid}/permissions")
      ->willReturn($permissions)
      ->shouldBeCalled();
    return $permissions;
  }

  protected function mockGetGitLabProjects($applicationUuid, $gitlabProjectId, $mockedGitlabProjects): Projects|ObjectProphecy {
    $projects = $this->prophet->prophesize(Projects::class);
    $projects->all(['search' => $applicationUuid])
      ->willReturn($mockedGitlabProjects);
    $projects->all()
      ->willReturn([$this->getMockedGitLabProject($gitlabProjectId)]);
    return $projects;
  }

  /**
   * @return array[]
   */
  protected function getMockGitLabVariables(): array {
    return [
      0 => [
          'environment_scope' => '*',
          'key' => 'ACQUIA_APPLICATION_UUID',
          'masked' => FALSE,
          'protected' => FALSE,
          'value' => '2b3f7cf0-6602-4590-948b-3b07b1b005ef',
          'variable_type' => 'env_var',
        ],
      1 => [
          'environment_scope' => '*',
          'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
          'masked' => FALSE,
          'protected' => FALSE,
          'value' => '111aae74-e81a-4052-b4b9-a27a62e6b6a6',
          'variable_type' => 'env_var',
        ],
    ];
  }

  /**
   * Normalize strings for Windows tests.
   *
   * @todo Remove for PHPUnit 10.
   */
  final public static function assertStringContainsStringIgnoringLineEndings(string $needle, string $haystack, string $message = ''): void {
    $haystack = strtr(
      $haystack,
      [
        "\r"   => "\n",
        "\r\n" => "\n",
      ]
    );
    static::assertThat($haystack, new StringContains($needle, FALSE), $message);
  }

}
