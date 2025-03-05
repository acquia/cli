<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioWizardCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\Commands\WizardTestBase;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Connector\Connector;
use DateTime;
use Gitlab\Api\Groups;
use Gitlab\Api\ProjectNamespaces;
use Gitlab\Api\Schedules;
use Gitlab\Client;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @property \Acquia\Cli\Command\CodeStudio\CodeStudioWizardCommand $command
 * @requires OS linux|darwin
 */
class CodeStudioWizardCommandTest extends WizardTestBase
{
    use IdeRequiredTestTrait;

    private string $gitLabHost = 'gitlabhost';

    private string $gitLabToken = 'gitlabtoken';

    private static int $gitLabProjectId = 33;

    private int $gitLabTokenId = 118;

    public function setUp(): void
    {
        parent::setUp();
        $this->mockApplicationRequest();
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
            [
                // One project.
                [self::getMockedGitLabProject(self::$gitLabProjectId)],
                // Inputs.
                [
                    0,
                    0,
                    // Do you want to continue?
                    'y',
                    // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
                    'y',
                ],
                // Args.
                [
                    '--key' => self::$key,
                    '--secret' => self::$secret,
                ],
            ],
            // Two projects.
            [
                [
                    self::getMockedGitLabProject(self::$gitLabProjectId),
                    self::getMockedGitLabProject(self::$gitLabProjectId),
                ],
                // Inputs.
                [
                    0,
                    0,
                    'n',
                    // Found multiple projects that could match the Sample application 1 application. Choose which one to configure.
                    '0',
                    // Do you want to continue?
                    'y',
                    // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
                    'y',
                ],
                // Args.
                [
                    '--key' => self::$key,
                    '--secret' => self::$secret,
                ],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    0,
                    0,
                    // 'Would you like to create a new Code Studio project?
                    'y',
                    // Select a project type Drupal_project.
                    '0',
                    // Select PHP version 8.1.
                    '0',
                    // Do you want to continue?
                    'y',
                    // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
                    'y',
                ],
                // Args.
                [
                    '--key' => self::$key,
                    '--secret' => self::$secret,
                ],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    // Select a project type Drupal_project.
                    '0',
                    // Select PHP version 8.2.
                    '1',
                    // Do you want to continue?
                    'y',
                    // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
                    'y',
                ],
                // Args.
                [
                    '--key' => self::$key,
                    '--secret' => self::$secret,
                ],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    // Select a project type Node_project.
                    '1',
                    // Select NODE version 18.
                    '0',
                    // Do you want to continue?
                    'y',
                    // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
                    'y',
                ],
                // Args.
                [
                    '--key' => self::$key,
                    '--secret' => self::$secret,
                ],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    // Select a project type Node_project.
                    '1',
                    // Select NODE version 20.
                    '1',
                    // Do you want to continue?
                    'y',
                    // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
                    'y',
                ],
                // Args.
                [
                    '--key' => self::$key,
                    '--secret' => self::$secret,
                ],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    // Select a project type Node_project.
                    '1',
                    // Select NODE version 22.
                    '2',
                    // Do you want to continue?
                    'y',
                    // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
                    'y',
                ],
                // Args.
                [
                    '--key' => self::$key,
                    '--secret' => self::$secret,
                ],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    0,
                    0,
                    'y',
                    'y',
                    // Choose project.
                    '0',
                    // Do you want to continue?
                    'y',
                ],
                // Args.
                [
                    '--key' => self::$key,
                    '--secret' => self::$secret,
                ],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    // Enter Cloud Key.
                    self::$key,
                    // Enter Cloud secret,.
                    self::$secret,
                    // Select a project type Drupal_project.
                    '0',
                    // Select PHP version 8.1.
                    '0',
                    // Do you want to continue?
                    'y',
                ],
                // Args.
                [],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    // Enter Cloud Key.
                    self::$key,
                    // Enter Cloud secret,.
                    self::$secret,
                    // Select a project type Node_project.
                    '1',
                    // Select NODE version 18.
                    '0',
                    // Do you want to continue?
                    'y',
                ],
                // Args.
                [],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    // Enter Cloud Key.
                    self::$key,
                    // Enter Cloud secret,.
                    self::$secret,
                    // Select a project type Drupal_project.
                    '0',
                    // Select PHP version 8.2.
                    '1',
                    // Do you want to continue?
                    'y',
                ],
                // Args.
                [],
            ],
            [
                // No projects.
                [],
                // Inputs.
                [
                    // Enter Cloud Key.
                    self::$key,
                    // Enter Cloud secret,.
                    self::$secret,
                    // Select a project type Node_project.
                    '1',
                    // Select NODE version 20.
                    '1',
                    // Do you want to continue?
                    'y',
                ],
                // Args.
                [],
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
        $this->mockGitLabPermissionsRequest($this::$applicationUuid);

        $gitlabClient = $this->prophet->prophesize(Client::class);
        $this->mockGitLabUsersMe($gitlabClient);
        $this->mockGitLabGroups($gitlabClient);
        $this->mockGitLabNamespaces($gitlabClient);

        $projects = $this->mockGetGitLabProjects($this::$applicationUuid, self::$gitLabProjectId, $mockedGitlabProjects);
        $parameters = [
            'container_registry_access_level' => 'disabled',
            'default_branch' => 'main',
            'description' => 'Source repository for Acquia Cloud Platform application <comment>a47ac10b-58cc-4372-a567-0e02b2c3d470</comment>',
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
            'description' => 'Source repository for Acquia Cloud Platform application <comment>a47ac10b-58cc-4372-a567-0e02b2c3d470</comment>',
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

        if (($inputs[0] === '1' || (array_key_exists(2, $inputs) && $inputs[2] === '1'))) {
            $parameters = [
                'ci_config_path' => 'gitlab-ci/Auto-DevOps.acquia.gitlab-ci.yml@acquia/node-template',
            ];
            $projects->update(self::$gitLabProjectId, $parameters)
                ->willReturn(true)
                ->shouldBeCalled();
        } else {
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

        /** @var Filesystem|ObjectProphecy $fileSystem */
        $fileSystem = $this->prophet->prophesize(Filesystem::class);
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
            '--key' => self::$key,
            '--secret' => self::$secret,
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
            '--key' => self::$key,
            '--secret' => self::$secret,
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
}
