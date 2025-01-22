<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\Api\ApiCommandFactory;
use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\CommandFactoryInterface;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use AcquiaCloudApi\Response\DatabaseResponse;
use Exception;
use Gitlab\Api\Projects;
use Gitlab\Api\Users;
use Gitlab\Exception\RuntimeException;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\StreamInterface;
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
abstract class CommandTestBase extends TestBase
{
    /**
     * The command tester.
     */
    private CommandTester $commandTester;

    // Select the application / SSH key / etc.
    protected static int $INPUT_DEFAULT_CHOICE = 0;

    protected CommandBase $command;

    protected string $apiCommandPrefix = 'api';

    abstract protected function createCommand(): CommandBase;

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        if (!isset($this->command)) {
            $this->command = $this->createCommand();
        }
        $this->printTestName();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (!in_array('brokenProphecy', $this->groups())) {
            $this->prophet->checkPredictions();
        }
    }

    protected function setCommand(CommandBase $command): void
    {
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
    protected function executeCommand(array $args = [], array $inputs = [], int $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE, ?bool $interactive = true): void
    {
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
            $tester->execute($args, ['verbosity' => $verbosity, 'interactive' => $interactive]);
        } catch (Exception $e) {
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
     */
    protected function getCommandTester(): CommandTester
    {
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
     */
    protected function getDisplay(): string
    {
        return $this->getCommandTester()->getDisplay();
    }

    /**
     * Gets the status code returned by the last execution of the command.
     */
    protected function getStatusCode(): int
    {
        return $this->getCommandTester()->getStatusCode();
    }

    /**
     * Write full width line.
     */
    protected function writeFullWidthLine(string $message, OutputInterface $output): void
    {
        $terminalWidth = (new Terminal())->getWidth();
        $paddingLen = (int) (($terminalWidth - strlen($message)) / 2);
        $pad = $paddingLen > 0 ? str_repeat('-', $paddingLen) : '';
        $output->writeln("<comment>$pad$message$pad</comment>");
    }

    /**
     * Prints the name of the PHPUnit test to output.
     */
    protected function printTestName(): void
    {
        if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
            $this->consoleOutput->writeln("");
            $this->writeFullWidthLine(get_class($this) . "::" . $this->name(), $this->consoleOutput);
        }
    }

    protected function getTargetGitConfigFixture(): string
    {
        return Path::join($this->projectDir, '.git', 'config');
    }

    protected function getSourceGitConfigFixture(): string
    {
        return Path::join($this->realFixtureDir, 'git_config');
    }

    /**
     * Creates a mock .git/config.
     */
    protected function createMockGitConfigFile(): void
    {
        // Create mock git config file.
        $this->fs->copy($this->getSourceGitConfigFixture(), $this->getTargetGitConfigFixture());
    }

    /**
     * Remove mock .git/config.
     */
    protected function removeMockGitConfig(): void
    {
        $this->fs->remove([
            $this->getTargetGitConfigFixture(),
            dirname($this->getTargetGitConfigFixture()),
        ]);
    }

    protected function mockReadIdePhpVersion(string $phpVersion = '7.1'): LocalMachineHelper|ObjectProphecy
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->getLocalFilepath(Path::join($this->dataDir, 'acquia-cli.json'))
            ->willReturn(Path::join($this->dataDir, 'acquia-cli.json'));
        $localMachineHelper->readFile('/home/ide/configs/php/.version')
            ->willReturn("$phpVersion\n")
            ->shouldBeCalled();

        return $localMachineHelper;
    }

    /**
     * @return array<mixed>
     */
    protected static function inputChooseEnvironment(): array
    {
        return [
            // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
            'n',
            // Select a Cloud Platform application:
            self::$INPUT_DEFAULT_CHOICE,
            // Would you like to link the project at ... ?
            'n',
            // Choose an Acquia environment:
            self::$INPUT_DEFAULT_CHOICE,
        ];
    }

    public function mockGetEnvironment(): mixed
    {
        $applications = $this->mockRequest('getApplications');
        $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
        $environments = $this->mockRequest('getApplicationEnvironments', $application->uuid);
        return $environments[self::$INPUT_DEFAULT_CHOICE];
    }

    protected function mockLocalMachineHelper(): LocalMachineHelper|ObjectProphecy
    {
        $localMachineHelper = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelper->useTty()->willReturn(false);
        $this->command->localMachineHelper = $localMachineHelper->reveal();

        return $localMachineHelper;
    }

    /**
     * @return \Acquia\Cli\Helpers\SshHelper|\Prophecy\Prophecy\ObjectProphecy
     */
    protected function mockSshHelper(): SshHelper|ObjectProphecy
    {
        return $this->prophet->prophesize(SshHelper::class);
    }

    protected function mockGetEnvironments(): object
    {
        $environmentResponse = $this->getMockEnvironmentResponse();
        $this->clientProphecy->request(
            'get',
            "/environments/" . $environmentResponse->id
        )
            ->willReturn($environmentResponse)
            ->shouldBeCalled();
        return $environmentResponse;
    }

    public function mockAcsfEnvironmentsRequest(
        object $applicationsResponse
    ): object {
        $environmentsResponse = self::getMockEnvironmentsResponse();
        foreach ($environmentsResponse->_embedded->items as $environment) {
            $environment->ssh_url = 'profserv2.01dev@profserv201dev.ssh.enterprise-g1.acquia-sites.com';
            $environment->domains = ["profserv201dev.enterprise-g1.acquia-sites.com"];
        }
        $this->clientProphecy->request(
            'get',
            "/applications/{$applicationsResponse->{'_embedded'}->items[0]->uuid}/environments"
        )
            ->willReturn($environmentsResponse->_embedded->items)
            ->shouldBeCalled();

        return $environmentsResponse;
    }

    /**
     * @return array<mixed>
     */
    protected function mockGetAcsfSites(mixed $sshHelper, bool $existAcsfSites = true): array
    {
        $acsfMultisiteFetchProcess = $this->mockProcess();
        if ($existAcsfSites) {
            $multisiteConfig = file_get_contents(Path::join($this->realFixtureDir, '/multisite-config.json'));
        } else {
            $multisiteConfig = file_get_contents(Path::join($this->realFixtureDir, '/no-multisite-config.json'));
        }
        $acsfMultisiteFetchProcess->getOutput()
            ->willReturn($multisiteConfig)
            ->shouldBeCalled();
        $sshHelper->executeCommand(
            Argument::type('string'),
            ['cat', '/var/www/site-php/profserv2.01dev/multisite-config.json'],
            false
        )->willReturn($acsfMultisiteFetchProcess->reveal())->shouldBeCalled();

        return json_decode($multisiteConfig, true);
    }

    protected function mockGetCloudSites(mixed $sshHelper, mixed $environment): void
    {
        $cloudMultisiteFetchProcess = $this->mockProcess();
        $cloudMultisiteFetchProcess->getOutput()
            ->willReturn("\nbar\ndefault\nfoo\n")
            ->shouldBeCalled();
        $parts = explode('.', $environment->ssh_url);
        $sitegroup = reset($parts);
        $sshHelper->executeCommand(
            Argument::type('string'),
            ['ls', "/mnt/files/$sitegroup.$environment->name/sites"],
            false
        )->willReturn($cloudMultisiteFetchProcess->reveal())->shouldBeCalled();
    }

    protected function mockProcess(bool $success = true): Process|ObjectProphecy
    {
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn($success);
        $process->getExitCode()->willReturn($success ? 0 : 1);
        if (!$success) {
            $process->getErrorOutput()->willReturn('error');
        } else {
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
        $databasesResponseJson = json_decode(file_get_contents(Path::join($this->realFixtureDir, '/acsf_db_response.json')), false, 512, JSON_THROW_ON_ERROR);
        $databasesResponse = array_map(
            static function (mixed $databaseResponse) {
                return new DatabaseResponse($databaseResponse);
            },
            $databasesResponseJson
        );
        $this->clientProphecy->request(
            'get',
            "/environments/$environmentsResponse->id/databases"
        )
            ->willReturn($databasesResponse)
            ->shouldBeCalled();

        return $databasesResponse;
    }

    protected function mockDatabaseBackupsResponse(
        object $environmentsResponse,
        string $dbName,
        int $backupId,
        bool $existingBackups = true
    ): object {
        $databaseBackupsResponse = self::getMockResponseFromSpec('/environments/{environmentId}/databases/{databaseName}/backups', 'get', 200);
        foreach ($databaseBackupsResponse->_embedded->items as $backup) {
            $backup->_links->download->href = "/environments/$environmentsResponse->id/databases/$dbName/backups/$backupId/actions/download";
            $backup->database->name = $dbName;
            // Acquia PHP SDK mutates the property name. Gross workaround, is there a better way?
            $backup->completedAt = $backup->completed_at;
        }

        if ($existingBackups) {
            $this->clientProphecy->request(
                'get',
                "/environments/$environmentsResponse->id/databases/$dbName/backups"
            )
                ->willReturn($databaseBackupsResponse->_embedded->items)
                ->shouldBeCalled();
        } else {
            $this->clientProphecy->request(
                'get',
                "/environments/$environmentsResponse->id/databases/$dbName/backups"
            )
                ->willReturn([], $databaseBackupsResponse->_embedded->items)
                ->shouldBeCalled();
        }

        return $databaseBackupsResponse;
    }

    protected function mockDownloadBackupResponse(
        mixed $environmentsResponse,
        mixed $dbName,
        mixed $backupId
    ): void {
        $stream = $this->prophet->prophesize(StreamInterface::class);
        $this->clientProphecy->stream('get', "/environments/$environmentsResponse->id/databases/$dbName/backups/$backupId/actions/download", [])
            ->willReturn($stream->reveal())
            ->shouldBeCalled();
    }

    protected function mockDatabaseBackupCreateResponse(
        mixed $environmentsResponse,
        mixed $dbName
    ): mixed {
        $backupCreateResponse = self::getMockResponseFromSpec('/environments/{environmentId}/databases/{databaseName}/backups', 'post', 202)->{'Creating backup'}->value;
        $this->clientProphecy->request('post', "/environments/$environmentsResponse->id/databases/$dbName/backups")
            ->willReturn($backupCreateResponse)
            ->shouldBeCalled();

        return $backupCreateResponse;
    }

    protected function mockNotificationResponseFromObject(object $responseWithNotificationLink, ?bool $success = true): array|object
    {
        $uuid = substr($responseWithNotificationLink->_links->notification->href, -36);
        if ($success) {
            return $this->mockRequest('getNotificationByUuid', $uuid);
        }

        return $this->mockRequest('getNotificationByUuid', $uuid, null, null, function ($response): void {
            $response->status = 'failed';
        });
    }

    protected function mockCreateMySqlDumpOnLocal(ObjectProphecy $localMachineHelper, bool $printOutput = true, bool $pv = true): void
    {
        $localMachineHelper->checkRequiredBinariesExist(["mysqldump", "gzip"])
            ->shouldBeCalled();
        $process = $this->mockProcess();
        $process->getOutput()->willReturn('');
        if ($pv) {
            $command = 'bash -c "set -o pipefail; MYSQL_PWD=drupal mysqldump --host=localhost --user=drupal drupal | pv --rate --bytes | gzip -9 > ' . sys_get_temp_dir() . '/acli-mysql-dump-drupal.sql.gz"';
        } else {
            $command = 'bash -c "set -o pipefail; MYSQL_PWD=drupal mysqldump --host=localhost --user=drupal drupal | gzip -9 > ' . sys_get_temp_dir() . '/acli-mysql-dump-drupal.sql.gz"';
        }
        $localMachineHelper->executeFromCmd($command, Argument::type('callable'), null, $printOutput)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecutePvExists(
        ObjectProphecy $localMachineHelper,
        bool $pvExists = true
    ): void {
        $localMachineHelper
            ->commandExists('pv')
            ->willReturn($pvExists)
            ->shouldBeCalled();
    }

    protected function mockExecuteGlabExists(
        ObjectProphecy $localMachineHelper
    ): void {
        $localMachineHelper
            ->commandExists('glab')
            ->willReturn(true)
            ->shouldBeCalled();
    }

    protected function mockPollCloudViaSsh(array $environmentsResponse, bool $ssh = true): ObjectProphecy
    {
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $process->getExitCode()->willReturn(0);
        $gitProcess = $this->prophet->prophesize(Process::class);
        $gitProcess->isSuccessful()->willReturn(true);
        $gitProcess->getExitCode()->willReturn(128);
        $sshHelper = $this->mockSshHelper();
        // Mock Git.
        $urlParts = explode(':', $environmentsResponse[0]->vcs->url);
        $sshHelper->executeCommand($urlParts[0], ['ls'], false)
            ->willReturn($gitProcess->reveal())
            ->shouldBeCalled();
        if ($ssh) {
            // Mock non-prod.
            $sshHelper->executeCommand($environmentsResponse[0]->ssh_url, ['ls'], false)
                ->willReturn($process->reveal())
                ->shouldBeCalled();
            // Mock prod.
            $sshHelper->executeCommand($environmentsResponse[1]->ssh_url, ['ls'], false)
                ->willReturn($process->reveal())
                ->shouldBeCalled();
        }
        return $sshHelper;
    }

    protected function mockPollCloudGitViaSsh(object $environmentResponse): ObjectProphecy
    {
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $process->getExitCode()->willReturn(128);
        $sshHelper = $this->mockSshHelper();
        $sshHelper->executeCommand($environmentResponse->vcs->url, ['ls'], false)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
        return $sshHelper;
    }

    protected function mockGetLocalSshKey(mixed $localMachineHelper, mixed $fileSystem, mixed $publicKey): string
    {
        $fileSystem->exists(Argument::type('string'))->willReturn(true);
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
        $finder->getIterator()
            ->willReturn(new \ArrayIterator([$file->reveal()]));
        $localMachineHelper->getFinder()->willReturn($finder);

        return $fileName;
    }

    /**
     * @return \Acquia\Cli\Command\Api\ApiCommandFactory
     */
    protected function getCommandFactory(): CommandFactoryInterface
    {
        return new ApiCommandFactory(
            $this->localMachineHelper,
            $this->datastoreCloud,
            $this->datastoreAcli,
            $this->cloudCredentials,
            $this->telemetryHelper,
            $this->projectDir,
            $this->clientServiceProphecy->reveal(),
            $this->sshHelper,
            $this->sshDir,
            $this->logger,
            $this->selfUpdateManager,
        );
    }

    /**
     * @return array<mixed>
     */
    protected function getApiCommands(): array
    {
        $apiCommandHelper = new ApiCommandHelper($this->logger);
        $commandFactory = $this->getCommandFactory();
        return $apiCommandHelper->getApiCommands(self::$apiSpecFixtureFilePath, $this->apiCommandPrefix, $commandFactory);
    }

    protected function getApiCommandByName(string $name): ApiBaseCommand|null
    {
        $apiCommands = $this->getApiCommands();
        foreach ($apiCommands as $apiCommand) {
            if ($apiCommand->getName() === $name) {
                return $apiCommand;
            }
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    protected static function getMockedGitLabProject(int $projectId): array
    {
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
    protected function mockGitLabAuthenticate(ObjectProphecy|LocalMachineHelper $localMachineHelper, string $gitlabHost, string $gitlabToken): ObjectProphecy|\Gitlab\Client
    {
        $this->mockGitlabGetHost($localMachineHelper, $gitlabHost);
        $this->mockGitlabGetToken($localMachineHelper, $gitlabToken, $gitlabHost);
        $gitlabClient = $this->prophet->prophesize(\Gitlab\Client::class);
        $gitlabClient->users()->willThrow(RuntimeException::class);
        return $gitlabClient;
    }

    protected function mockGitlabGetToken(mixed $localMachineHelper, string $gitlabToken, string $gitlabHost, bool $success = true): void
    {
        $process = $this->mockProcess($success);
        $process->getOutput()->willReturn($gitlabToken);
        $localMachineHelper->execute([
            'glab',
            'config',
            'get',
            'token',
            '--host=' . $gitlabHost,
        ], null, null, false)->willReturn($process->reveal());
    }

    protected function mockGitlabGetHost(mixed $localMachineHelper, string $gitlabHost): void
    {
        $process = $this->mockProcess();
        $process->getOutput()->willReturn($gitlabHost);
        $localMachineHelper->execute([
            'glab',
            'config',
            'get',
            'host',
        ], null, null, false)->willReturn($process->reveal());
    }

    protected function mockGitLabUsersMe(ObjectProphecy|\Gitlab\Client $gitlabClient): void
    {
        $users = $this->prophet->prophesize(Users::class);
        $me = [
            'avatar_url' => 'https://secure.gravatar.com/avatar/5ee7b8ad954bf7156e6eb57a45d60dec?s=80&d=identicon',
            'bio' => '',
            'bot' => false,
            'can_create_group' => true,
            'can_create_project' => true,
            'color_scheme_id' => 1,
            'commit_email' => 'matthew.grasmick@acquia.com',
            'confirmed_at' => '2021-12-21T02:26:51.898Z',
            'created_at' => '2021-12-21T02:26:52.240Z',
            'current_sign_in_at' => '2022-01-22T01:40:55.418Z',
            'email' => 'matthew.grasmick@acquia.com',
            'external' => false,
            'followers' => 0,
            'following' => 0,
            'id' => 20,
            'identities' => [],
            'is_admin' => true,
            'job_title' => '',
            'last_activity_on' => '2022-01-22',
            'last_sign_in_at' => '2022-01-21T23:00:49.035Z',
            'linkedin' => '',
            'local_time' => '2:00 AM',
            'location' => null,
            'name' => 'Matthew Grasmick',
            'note' => '',
            'organization' => null,
            'private_profile' => false,
            'projects_limit' => 100000,
            'pronouns' => null,
            'public_email' => '',
            'skype' => '',
            'state' => 'active',
            'theme_id' => 1,
            'twitter' => '',
            'two_factor_enabled' => false,
            'username' => 'matthew.grasmick',
            'website_url' => '',
            'web_url' => 'https://code.dev.cloudservices.acquia.io/matthew.grasmick',
            'work_information' => null,
        ];
        $users->me()->willReturn($me);
        $gitlabClient->users()->willReturn($users->reveal());
    }

    /**
     * @param $applicationUuid
     * @return array<mixed>
     */
    protected function mockGitLabPermissionsRequest(mixed $applicationUuid): array
    {
        $permissionsResponse = self::getMockResponseFromSpec('/applications/{applicationUuid}/permissions', 'get', 200);
        $permissions = $permissionsResponse->_embedded->items;
        $permission = clone reset($permissions);
        $permission->name = "administer environment variables on non-prod";
        $permissions[] = $permission;
        $this->clientProphecy->request('get', "/applications/$applicationUuid/permissions")
            ->willReturn($permissions)
            ->shouldBeCalled();
        return $permissions;
    }

    protected function mockGetGitLabProjects(mixed $applicationUuid, mixed $gitlabProjectId, mixed $mockedGitlabProjects): Projects|ObjectProphecy
    {
        $projects = $this->prophet->prophesize(Projects::class);
        $projects->all(['search' => $applicationUuid])
            ->willReturn($mockedGitlabProjects);
        $projects->all()
            ->willReturn([self::getMockedGitLabProject($gitlabProjectId)]);
        return $projects;
    }

    /**
     * @return array<mixed>
     */
    protected function getMockGitLabVariables(): array
    {
        return [
            0 => [
                'environment_scope' => '*',
                'key' => 'ACQUIA_APPLICATION_UUID',
                'masked' => true,
                'protected' => false,
                'value' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
                'variable_type' => 'env_var',
            ],
            1 => [
                'environment_scope' => '*',
                'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
                'masked' => true,
                'protected' => false,
                'value' => '17feaf34-5d04-402b-9a67-15d5161d24e1',
                'variable_type' => 'env_var',
            ],
            2 => [
                'key' => 'ACQUIA_CLOUD_API_TOKEN_SECRET',
                'masked' => false,
                'protected' => false,
                'value' => 'X1u\/PIQXtYaoeui.4RJSJpGZjwmWYmfl5AUQkAebYE=',
                'variable_type' => 'env_var',
            ],
        ];
    }
}
