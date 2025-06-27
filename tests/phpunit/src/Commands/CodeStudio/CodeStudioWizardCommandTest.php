<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioWizardCommand;
use Acquia\Cli\Command\CodeStudio\EntityType;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\Commands\WizardTestBase;
use AcquiaCloudApi\Connector\Connector;
use DateTime;
use Gitlab\Api\Groups;
use Gitlab\Api\ProjectNamespaces;
use Gitlab\Api\Schedules;
use Gitlab\Client;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @property \Acquia\Cli\Command\CodeStudio\CodeStudioWizardCommand $command
 * @requires OS linux|darwin
 */
class CodeStudioWizardCommandTest extends WizardTestBase
{
    use IdeRequiredTestTrait;

    private const ARG_KEY = '--key';
    private const ARG_SECRET = '--secret';

    private string $gitLabHost = 'gitlabhost';
    private string $gitLabToken = 'gitlabtoken';
    private static int $gitLabProjectId = 33;
    private int $gitLabTokenId = 118;

    public function setUp(): void
    {
        parent::setUp();
        self::setEnvVars(['GITLAB_HOST' => 'code.cloudservices.acquia.io']);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        self::unsetEnvVars(['GITLAB_HOST' => 'code.cloudservices.acquia.io']);
    }

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(CodeStudioWizardCommand::class);
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestCommand(): array
    {
        return [
            // Application: Drupal_project, PHP 8.1.
            [
                [self::getMockedGitLabProject(self::$gitLabProjectId)],
                [
                    // Entity type: Application.
                    0,
                    // Project type: Drupal_project.
                    0,
                    // MySQL version: 5.7.
                    0,
                    // PHP version: 8.1.
                    0,
                    // Do you want to continue?
                    'y',
                    // One time push?
                    'y',
                ],
                [
                    self::ARG_KEY => self::$key,
                    self::ARG_SECRET => self::$secret,
                ],
            ],
            // Application: Drupal_project, PHP 8.2.
            [
                [self::getMockedGitLabProject(self::$gitLabProjectId)],
                [
                    // Entity type: Application.
                    0,
                    // Project type: Drupal_project.
                    0,
                    // MySQL version: 5.7.
                    0,
                    // PHP version: 8.2.
                    1,
                    // Do you want to continue?
                    'y',
                    // One time push?
                    'y',
                ],
                [
                    self::ARG_KEY => self::$key,
                    self::ARG_SECRET => self::$secret,
                ],
            ],
            // Application: Drupal_project, PHP 8.3.
            [
                [self::getMockedGitLabProject(self::$gitLabProjectId)],
                [
                    // Entity type: Application.
                    0,
                    // Project type: Drupal_project.
                    0,
                    // MySQL version: 8.0.
                    1,
                    // PHP version: 8.3.
                    2,
                    // Do you want to continue?
                    'y',
                    // One time push?
                    'y',
                ],
                [
                    self::ARG_KEY => self::$key,
                    self::ARG_SECRET => self::$secret,
                ],
            ],
            // Application: Drupal_project, PHP 8.4.
            [
                [self::getMockedGitLabProject(self::$gitLabProjectId)],
                [
                    // Entity type: Application.
                    0,
                    // Project type: Drupal_project.
                    0,
                    // MySQL version: 8.0.
                    1,
                    // PHP version: 8.4.
                    3,
                    // Do you want to continue?
                    'y',
                    // One time push?
                    'y',
                ],
                [
                    self::ARG_KEY => self::$key,
                    self::ARG_SECRET => self::$secret,
                ],
            ],
            // Application: Node_project, Basic, Node 20.
            [
                [self::getMockedGitLabProject(self::$gitLabProjectId)],
                [
                    // Entity type: Application.
                    0,
                    // Project type: Node_project.
                    1,
                    // Hosting type: Basic.
                    0,
                    // Node version: 20.
                    0,
                    // Do you want to continue?
                    'y',
                    // One time push?
                    'y',
                ],
                [
                    self::ARG_KEY => self::$key,
                    self::ARG_SECRET => self::$secret,
                ],
            ],
            // Application: Node_project, Advanced, Node 22.
            [
                [self::getMockedGitLabProject(self::$gitLabProjectId)],
                [
                    // Entity type: Application.
                    0,
                    // Project type: Node_project.
                    1,
                    // Hosting type: Advanced.
                    1,
                    // Node version: 22.
                    1,
                    // Do you want to continue?
                    'y',
                    // One time push?
                    'y',
                ],
                [
                    self::ARG_KEY => self::$key,
                    self::ARG_SECRET => self::$secret,
                ],
            ],
        ];
    }

    /**
     * @dataProvider providerTestCommand
     */
    public function testCommand(array $mockedGitlabProjects, array $inputs, array $args): void
    {
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->mockRequest('getAccount');
        $this->mockApplicationRequest();
        $this->mockGitLabPermissionsRequest($this::$applicationUuid);

        $gitlabClient = $this->prophet->prophesize(Client::class);
        $this->mockGitLabUsersMe($gitlabClient);
        $this->mockGitLabGroups($gitlabClient);
        $this->mockGitLabNamespaces($gitlabClient);

        $projects = $this->mockGetGitLabProjects($this::$applicationUuid, self::$gitLabProjectId, $mockedGitlabProjects);
        $parameters = [
            'container_registry_access_level' => 'disabled',
            'default_branch' => 'main',
            'description' => 'Source repository for Acquia Cloud Platform Application <comment>a47ac10b-58cc-4372-a567-0e02b2c3d470</comment>',
            'initialize_with_readme' => true,
            'namespace_id' => 47,
            'topics' => 'Acquia Cloud Application',
        ];
        $projects->create('Sample-application-1', $parameters)
            ->willReturn(self::getMockedGitLabProject(self::$gitLabProjectId));
        $this->mockGitLabProjectsTokens($projects);
        $parameters = [
            'container_registry_access_level' => 'disabled',
            'default_branch' => 'main',
            'description' => 'Source repository for Acquia Cloud Platform Application <comment>a47ac10b-58cc-4372-a567-0e02b2c3d470</comment>',
            'initialize_with_readme' => true,
            'topics' => 'Acquia Cloud Application',
        ];
        $projects->update(self::$gitLabProjectId, $parameters)
            ->willReturn(true)
            ->shouldBeCalled();
        $projects->uploadAvatar(
            33,
            Argument::type('string'),
        )->willReturn(true)
            ->shouldBeCalled();
        $this->mockGitLabVariables(self::$gitLabProjectId, $projects);
        if ($inputs[0] === 0 && $inputs[1] === 1) {
            // Node project - set up Node template.
            $parameters = [
                'ci_config_path' => 'gitlab-ci/Auto-DevOps.acquia.gitlab-ci.yml@acquia/node-template',
            ];
            $projects->update(self::$gitLabProjectId, $parameters)
                ->willReturn(true)
                ->shouldBeCalled();
        } else {
            // Drupal project - set up Drupal template.
            $schedules = $this->prophet->prophesize(Schedules::class);
            $schedules->showAll(self::$gitLabProjectId)->willReturn([]);
            $pipeline = ['id' => 1];
            $parameters = [
                // Every Thursday at midnight.
                'cron' => '0 0 * * 4',
                'description' => 'Code Studio Automatic Updates',
                'ref' => 'master',
            ];
            $schedules->create(self::$gitLabProjectId, $parameters)
                ->willReturn($pipeline);
            $schedules->addVariable(self::$gitLabProjectId, $pipeline['id'], [
                'key' => 'ACQUIA_JOBS_DEPRECATED_UPDATE',
                'value' => 'true',
            ])->willReturn(true)->shouldBeCalled();
            $schedules->addVariable(self::$gitLabProjectId, $pipeline['id'], [
                'key' => 'ACQUIA_JOBS_COMPOSER_UPDATE',
                'value' => 'true',
            ])->willReturn(true)->shouldBeCalled();
            $gitlabClient->schedules()->willReturn($schedules->reveal());
        }

        $gitlabClient->projects()->willReturn($projects);

        $this->command->setGitLabClient($gitlabClient->reveal());

        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->checkRequiredBinariesExist(['git']);
        $this->mockExecuteGlabExists($localMachineHelper);
        $process = $this->mockProcess();
        $localMachineHelper->execute(Argument::containing('remote'), Argument::type('callable'), '/home/ide/project', false)
            ->willReturn($process->reveal());
        $localMachineHelper->execute(Argument::containing('push'), Argument::type('callable'), '/home/ide/project', false)
            ->willReturn($process->reveal());

        $this->mockGetCurrentBranchName($localMachineHelper);
        $this->mockGitlabGetHost($localMachineHelper, $this->gitLabHost);
        $this->mockGitlabGetToken($localMachineHelper, $this->gitLabToken, $this->gitLabHost);

        // Set properties and execute.
        $this->executeCommand($args, $inputs);
        $output = $this->getDisplay();
        $output_strings = $this->getOutputStrings();
        foreach ($output_strings as $output_string) {
            self::assertStringContainsString($output_string, $output);
        }
        self::assertStringNotContainsString('[ERROR]', $output);

        // Assertions.
        $this->assertEquals(0, $this->getStatusCode());
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestCommandCodebase(): array
    {
        return [
            // Codebase: Drupal_project, PHP 8.1.
            [
                [self::getMockedGitLabProject(self::$gitLabProjectId)],
                [
                    // Entity type: Codebase.
                    1,
                    // Project type: Drupal_project [Auto selected].
                    // MySQL version: 5.7.
                    0,
                    // PHP version: 8.1.
                    0,
                    // Codebase selection: first codebase (test).
                    0,
                    // Do you want to continue?
                    'y',
                    // One time push?
                    'y',
                ],
                [
                    self::ARG_KEY => self::$key,
                    self::ARG_SECRET => self::$secret,
                ],
            ],
        ];
    }

    /**
     * @dataProvider providerTestCommandCodebase
     */
    public function testCommandCodebase(array $mockedGitlabProjects, array $inputs, array $args): void
    {
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))
            ->shouldBeCalled();
        $this->mockRequest('getAccount');

        $this->mockCodebaseRequest();

        $gitlabClient = $this->prophet->prophesize(Client::class);
        $this->mockGitLabUsersMe($gitlabClient);
        $this->mockGitLabGroups($gitlabClient);
        $this->mockGitLabNamespaces($gitlabClient);

        $projects = $this->mockGetGitLabProjects($this::$applicationUuid, self::$gitLabProjectId, $mockedGitlabProjects);

        $parameters = [
            'container_registry_access_level' => 'disabled',
            'default_branch' => 'main',
            'description' => 'Source repository for Acquia Cloud Platform Application <comment>a47ac10b-58cc-4372-a567-0e02b2c3d470</comment>',
            'initialize_with_readme' => true,
            'namespace_id' => 47,
            'topics' => 'Acquia Cloud Application',
        ];

        $projects->create('Sample-application-1', $parameters)
            ->willReturn(self::getMockedGitLabProject(self::$gitLabProjectId));

        $this->mockGitLabProjectsTokens($projects);

        $parameters = [
            'container_registry_access_level' => 'disabled',
            'default_branch' => 'main',
            'description' => 'Source repository for Acquia Cloud Platform Codebase <comment>a47ac10b-58cc-4372-a567-0e02b2c3d470</comment>',
            'initialize_with_readme' => true,
            'topics' => 'Acquia Cloud Application',
        ];
        $projects->update(self::$gitLabProjectId, $parameters)
            ->willReturn(true)
            ->shouldBeCalled();

        $projects->uploadAvatar(
            33,
            Argument::type('string'),
        )->willReturn(true)
            ->shouldBeCalled();
        $this->mockGitLabVariablesCodebase(self::$gitLabProjectId, $projects);

        // Codebase scenario uses Drupal template (like application Drupal projects)
        $schedules = $this->prophet->prophesize(Schedules::class);
        $schedules->showAll(self::$gitLabProjectId)->willReturn([]);
        $pipeline = ['id' => 1];
        $parameters = [
            // Every Thursday at midnight.
            'cron' => '0 0 * * 4',
            'description' => 'Code Studio Automatic Updates',
            'ref' => 'master',
        ];
        $schedules->create(self::$gitLabProjectId, $parameters)
            ->willReturn($pipeline);
        $schedules->addVariable(self::$gitLabProjectId, $pipeline['id'], [
            'key' => 'ACQUIA_JOBS_DEPRECATED_UPDATE',
            'value' => 'true',
        ])->willReturn(true)->shouldBeCalled();
        $schedules->addVariable(self::$gitLabProjectId, $pipeline['id'], [
            'key' => 'ACQUIA_JOBS_COMPOSER_UPDATE',
            'value' => 'true',
        ])->willReturn(true)->shouldBeCalled();
        $gitlabClient->schedules()->willReturn($schedules->reveal());

        $gitlabClient->projects()->willReturn($projects);

        $this->command->setGitLabClient($gitlabClient->reveal());

        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->checkRequiredBinariesExist(['git']);
        $this->mockExecuteGlabExists($localMachineHelper);
        $process = $this->mockProcess();
        $localMachineHelper->execute(Argument::containing('remote'), Argument::type('callable'), '/home/ide/project', false)
            ->willReturn($process->reveal());
        $localMachineHelper->execute(Argument::containing('push'), Argument::type('callable'), '/home/ide/project', false)
            ->willReturn($process->reveal());

        $this->mockGetCurrentBranchName($localMachineHelper);
        $this->mockGitlabGetHost($localMachineHelper, $this->gitLabHost);
        $this->mockGitlabGetToken($localMachineHelper, $this->gitLabToken, $this->gitLabHost);

        // Execute the command.
        $this->executeCommand($args, $inputs);
        $output = $this->getDisplay();
        $this->mockRequest('getAccount');
        $output_strings = $this->getCommandOutput(self::getMockedGitLabProject(self::$gitLabProjectId), EntityType::Codebase);
        foreach ($output_strings as $output_string) {
            self::assertStringContainsString($output_string, $output);
        }
        self::assertStringNotContainsString('[ERROR]', $output);

        // Verify successful execution.
        $this->assertEquals(0, $this->getStatusCode());

        // Additional assertions specific to codebase scenario.
        self::assertStringContainsString('Codebase', $output);
    }

    /**
     * @group brokenProphecy
     */
    public function testInvalidGitLabCredentials(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteGlabExists($localMachineHelper);
        $gitlabClient = $this->mockGitLabAuthenticate($localMachineHelper, $this->gitLabHost, $this->gitLabToken);
        $this->command->setGitLabClient($gitlabClient->reveal());

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Unable to authenticate with Code Studio');
        $this->executeCommand([
            self::ARG_KEY => self::$key,
            self::ARG_SECRET => self::$secret,
        ]);
    }

    /**
     * @group brokenProphecy
     */
    public function testMissingGitLabCredentials(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteGlabExists($localMachineHelper);
        $this->mockGitlabGetHost($localMachineHelper, $this->gitLabHost);
        $this->mockGitlabGetToken($localMachineHelper, $this->gitLabToken, $this->gitLabHost, false);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Could not determine GitLab token');
        $this->executeCommand([
            self::ARG_KEY => self::$key,
            self::ARG_SECRET => self::$secret,
        ]);
    }

    protected function mockGitLabProjectsTokens(ObjectProphecy $projects): void
    {
        $tokens = [
            0 => [
                'access_level' => 40,
                'active' => true,
                'created_at' => '2021-12-28T20:08:21.629Z',
                'expires_at' => new DateTime('+365 days'),
                'id' => $this->gitLabTokenId,
                'name' => 'acquia-codestudio',
                'revoked' => false,
                'scopes' => [
                    0 => 'api',
                    1 => 'write_repository',
                ],
                'user_id' => 154,
            ],
        ];
        $projects->projectAccessTokens(self::$gitLabProjectId)
            ->willReturn($tokens)
            ->shouldBeCalled();
        $projects->deleteProjectAccessToken(self::$gitLabProjectId, $this->gitLabTokenId)
            ->willReturn(true)
            ->shouldBeCalled();
        $token = $tokens[0];
        $token['token'] = 'token';
        $projects->createProjectAccessToken(self::$gitLabProjectId, Argument::type('array'))
            ->willReturn($token);
    }

    protected function mockGetCurrentBranchName(mixed $localMachineHelper): void
    {
        $process = $this->mockProcess();
        $process->getOutput()->willReturn('main');
        $localMachineHelper->execute([
            'git',
            'rev-parse',
            '--abbrev-ref',
            'HEAD',
        ], null, null, false)->willReturn($process->reveal());
    }

    protected function mockGitLabGroups(ObjectProphecy $gitlabClient): void
    {
        $groups = $this->prophet->prophesize(Groups::class);
        $groups->all(Argument::type('array'))->willReturn([
            0 => [
                'auto_devops_enabled' => null,
                'avatar_url' => null,
                'created_at' => '2021-11-16T18:54:31.275Z',
                'default_branch_protection' => 2,
                'description' => '',
                'emails_disabled' => null,
                'full_name' => 'awesome-demo',
                'full_path' => 'awesome-demo',
                'id' => 47,
                'ldap_access' => null,
                'ldap_cn' => null,
                'lfs_enabled' => true,
                'marked_for_deletion_on' => null,
                'mentions_disabled' => null,
                'name' => 'awesome-demo',
                'parent_id' => null,
                'path' => 'awesome-demo',
                'project_creation_level' => 'developer',
                'request_access_enabled' => true,
                'require_two_factor_authentication' => false,
                'share_with_group_lock' => false,
                'subgroup_creation_level' => 'maintainer',
                'two_factor_grace_period' => 48,
                'visibility' => 'private',
                'web_url' => 'https://code.cloudservices.acquia.io/groups/awesome-demo',
            ],
            1 => [
                'auto_devops_enabled' => null,
                'avatar_url' => null,
                'created_at' => '2021-12-14T18:49:50.724Z',
                'default_branch_protection' => 2,
                'description' => '',
                'emails_disabled' => null,
                'full_name' => 'Nestle',
                'full_path' => 'nestle',
                'id' => 68,
                'ldap_access' => null,
                'ldap_cn' => null,
                'lfs_enabled' => true,
                'marked_for_deletion_on' => null,
                'mentions_disabled' => null,
                'name' => 'Nestle',
                'parent_id' => null,
                'path' => 'nestle',
                'project_creation_level' => 'developer',
                'request_access_enabled' => true,
                'require_two_factor_authentication' => false,
                'share_with_group_lock' => false,
                'subgroup_creation_level' => 'maintainer',
                'two_factor_grace_period' => 48,
                'visibility' => 'private',
                'web_url' => 'https://code.cloudservices.acquia.io/groups/nestle',
            ],
        ]);
        $gitlabClient->groups()->willReturn($groups->reveal());
    }

    protected function mockGitLabNamespaces(ObjectProphecy $gitlabClient): void
    {
        $namespaces = $this->prophet->prophesize(ProjectNamespaces::class);
        $namespaces->show(Argument::type('string'))->willReturn([
            'avatar_url' => 'https://secure.gravatar.com/avatar/5ee7b8ad954bf7156e6eb57a45d60dec?s=80&d=identicon',
            'billable_members_count' => 1,
            'full_path' => 'matthew.grasmick',
            'id' => 48,
            'kind' => 'user',
            'max_seats_used' => 0,
            'name' => 'Matthew Grasmick',
            'parent_id' => null,
            'path' => 'matthew.grasmick',
            'plan' => 'default',
            'seats_in_use' => 0,
            'trial' => false,
            'trial_ends_on' => null,
            'web_url' => 'https://code.cloudservices.acquia.io/matthew.grasmick',
        ]);
        $gitlabClient->namespaces()->willReturn($namespaces->reveal());
    }

    protected function mockGitLabVariables(int $gitlabProjectId, ObjectProphecy $projects): void
    {
        $variables = $this->getMockGitLabVariables();
        $projects->variables($gitlabProjectId)->willReturn($variables);
        foreach ($variables as $variable) {
            $projects->addVariable($gitlabProjectId, Argument::type('string'), Argument::type('string'), Argument::type('bool'), null, [
                'masked' => $variable['masked'],
                'variable_type' => $variable['variable_type'],
            ])->willReturn(true)->shouldBeCalled();
        }
        foreach ($variables as $variable) {
            $projects->updateVariable(self::$gitLabProjectId, $variable['key'], $variable['value'], false, null, [
                'masked' => true,
                'variable_type' => 'env_var',
            ])->willReturn(true)
                ->shouldBeCalled();
        }
    }

    protected function mockGitLabVariablesCodebase(int $gitlabProjectId, ObjectProphecy $projects): void
    {
        $variables = $this->getMockGitLabVariablesCodebase();
        $projects->variables($gitlabProjectId)->willReturn($variables);
        foreach ($variables as $variable) {
            $projects->addVariable($gitlabProjectId, Argument::type('string'), Argument::type('string'), Argument::type('bool'), null, [
                'masked' => $variable['masked'],
                'variable_type' => $variable['variable_type'],
            ])->willReturn(true)->shouldBeCalled();
        }
        foreach ($variables as $variable) {
            $projects->updateVariable(self::$gitLabProjectId, $variable['key'], $variable['value'], false, null, [
                'masked' => true,
                'variable_type' => 'env_var',
            ])->willReturn(true)
                ->shouldBeCalled();
        }
    }

    /**
     * Returns mock GitLab variables specifically for codebase scenarios.
     *
     * @return array<mixed>
     */
    protected function getMockGitLabVariablesCodebase(): array
    {
        return [
            0 => [
                'environment_scope' => '*',
                'key' => 'ACQUIA_CODEBASE_UUID',
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

    protected function mockCodebaseRequest(): object
    {
        $codebasesArray = [
            (object) [
                'applications_total' => 0,
                'created_at' => '2024-12-20T06:39:50.000Z',
                'description' => '',
                'flags' => (object) [
                    'active' => 1,
                ],
                'hash' => 'ryh4smn',
                'id' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
                'label' => 'Sample-application-1',
                'region' => 'us-east-1',
                'repository_id' => 'a5ef0a9d-44ce-4f06-8d4f-15f24f941a74',
                'updated_at' => '2024-12-20T06:39:50.000Z',
                'vcs_url' => 'ssh://us-east-1.dev.vcs.acquia.io/a47ac10b-58cc-4372-a567-0e02b2c3d470',
                '_embedded' => (object) [
                    'subscription' => (object) [
                        'id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
                        '_links' => (object) [
                            'self' => (object) [
                                'href' => 'https://cloud.acquia.com/api/subscriptions/f47ac10b-58cc-4372-a567-0e02b2c3d479',
                            ],
                        ],
                    ],
                ],
                '_links' => (object) [
                    'applications' => (object) [
                        'href' => 'https://cloud.acquia.com/api/codebases/a47ac10b-58cc-4372-a567-0e02b2c3d470/applications',
                    ],
                    'self' => (object) [
                        'href' => 'https://cloud.acquia.com/api/codebases',
                    ],
                    'subscription' => (object) [
                        'href' => 'https://cloud.acquia.com/api/subscriptions/f47ac10b-58cc-4372-a567-0e02b2c3d479',
                    ],
                ],
            ],
        ];

        $this->clientProphecy->request('get', '/codebases')
            ->willReturn($codebasesArray)
            ->shouldBeCalled();

        $codebaseResponse = $codebasesArray[0];
        $this->clientProphecy->request(
            'get',
            '/codebases/' . $codebaseResponse->id
        )
            ->willReturn($codebaseResponse)
            ->shouldBeCalled();

        return $codebaseResponse;
    }
    /**
     * Returns the output strings expected from the command execution.
     *
     * @return array<string>
     */
    private function getCommandOutput(array|string $project, EntityType $entityType, string $entityName = 'Sample-application-1', string $cloudUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470'): array
    {
        return [
            "Selected Drupal project by default for Codebases",
            "This command will configure the Code Studio project {$project['path_with_namespace']} for automatic deployment to the",
            "Acquia Cloud Platform $entityType->value $entityName ($cloudUuid)",
            "using credentials (API Token and SSH Key) belonging to jane.doe@example.com.",
            "If the jane.doe@example.com Cloud account is deleted in the future, this Code Studio project will need to be re-configured.",
            "Setting GitLab CI/CD variables for",
            "Successfully configured the Code Studio project!",
            // "This project will now use Acquia's Drupal optimized AutoDevOps to build, test, and deploy your code automatically to Acquia Cloud Platform via CI/CD pipelines.",
            "You can visit it here:",
            $project['web_url'],
            "Next, you should use git to push code to your Code Studio project. E.g.,",
            "  git remote add codestudio {$project['http_url_to_repo']}",
            "  git push codestudio",
        ];
    }

    public function testGetRequiredCloudPermissionsReturnsExpectedPermissions(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getRequiredCloudPermissions');
        $method->setAccessible(true);

        $expected = [
            'deploy to non-prod',
            'add ssh key to git',
            'add ssh key to non-prod',
            'add an environment',
            'delete an environment',
            'administer environment variables on non-prod',
        ];

        $result = $method->invoke($this->command);

        $this->assertEquals($expected, $result);
    }
}
