<?php

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioWizardCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\Commands\WizardTestBase;
use Acquia\Cli\Tests\TestBase;
use Gitlab\Api\Groups;
use Gitlab\Api\ProjectNamespaces;
use Gitlab\Api\Schedules;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class CodeStudioWizardCommandTest.
 *
 * @property \Acquia\Cli\Command\CodeStudio\CodeStudioWizardCommand $command
 * @package Acquia\Cli\Tests\Commands
 *
 * @requires OS linux|darwin
 */
class CodeStudioWizardCommandTest extends WizardTestBase {

  use IdeRequiredTestTrait;

  private string $gitLabHost = 'gitlabhost';
  private string $gitLabToken = 'gitlabtoken';

  private int $gitLabProjectId = 33;
  private int $gitLabTokenId = 118;

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->mockApplicationRequest();
    TestBase::setEnvVars(['GITLAB_HOST' => 'code.cloudservices.acquia.io']);
  }

  public function tearDown(): void {
    parent::tearDown();
    TestBase::unsetEnvVars(['GITLAB_HOST' => 'code.cloudservices.acquia.io']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(CodeStudioWizardCommand::class);
  }

  /**
   * @return array
   */
  public function providerTestCommand() {
    return [
      [
        // One project.
        [$this->getMockedGitLabProject($this->gitLabProjectId)],
        // Inputs
        [
          // Do you want to continue?
          'y',
          // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
          'y',
        ],
        // Args.
        [
          '--key' => $this->key,
          '--secret' => $this->secret,
        ]
      ],
      // Two projects.
      [
        [$this->getMockedGitLabProject($this->gitLabProjectId), $this->getMockedGitLabProject($this->gitLabProjectId)],
        // Inputs.
        [
          //  Found multiple projects that could match the Sample application 1 application. Please choose which one to configure.
          '0',
          // Do you want to continue?
          'y',
          // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
          'y',
        ],
        // Args.
        [
          '--key' => $this->key,
          '--secret' => $this->secret,
        ]
      ],
      [
        // No projects.
        [],
        // Inputs.
        [
          // 'Would you like to create a new Code Studio project?
          'y',
          // Do you want to continue?
          'y',
          // Would you like to perform a one time push of code from Acquia Cloud to Code Studio now? (yes/no) [yes]:
          'y',
        ],
        // Args.
        [
          '--key' => $this->key,
          '--secret' => $this->secret,
        ]
      ],
      [
        // No projects.
        [],
        // Inputs
        [
          // 'Would you like to create a new Code Studio project?
          'n',
          // Choose project.
          '0',
          // Do you want to continue?
          'y',
        ],
        // Args
        [
          '--key' => $this->key,
          '--secret' => $this->secret,
        ]
      ],
      [
        // No projects.
        [],
        // Inputs
        [
          // 'Would you like to create a new Code Studio project?
          'y',
          // Enter Cloud Key
          $this->key,
          // Enter Cloud secret,
          $this->secret,
          // Do you want to continue?
          'y',
        ],
        // Args
        []
      ]
    ];
  }

  /**
   * @dataProvider providerTestCommand
   *
   * @param $mocked_gitlab_projects
   * @param $args
   * @param $inputs
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testCommand($mocked_gitlab_projects, $inputs, $args) {
    $environments_response = $this->getMockEnvironmentsResponse();
    $selected_environment = $environments_response->_embedded->items[0];
    $this->clientProphecy->request('get', "/applications/{$this::$application_uuid}/environments")->willReturn($environments_response->_embedded->items)->shouldBeCalled();
    $this->mockAccountRequest();
    $this->mockGitLabPermissionsRequest($this::$application_uuid);

    $gitlab_client = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlab_client);
    $this->mockGitLabGroups($gitlab_client);
    $this->mockGitLabNamespaces($gitlab_client);

    $projects = $this->mockGetGitLabProjects($this::$application_uuid, $this->gitLabProjectId, $mocked_gitlab_projects);
    $projects->create(Argument::type('string'), Argument::type('array'))->willReturn($this->getMockedGitLabProject());
    $this->mockGitLabProjectsTokens($projects);
    $projects->update($this->gitLabProjectId, Argument::type('array'));
    $this->mockGitLabVariables($this->gitLabProjectId, $projects);
    $schedules = $this->prophet->prophesize(Schedules::class);
    $schedules->showAll($this->gitLabProjectId)->willReturn([]);
    $pipeline = ['id' => 1];
    $schedules->create($this->gitLabProjectId, Argument::type('array'))->willReturn($pipeline);
    $schedules->addVariable($this->gitLabProjectId, $pipeline['id'], Argument::type('array'));
    $gitlab_client->schedules()->willReturn($schedules->reveal());
    $gitlab_client->projects()->willReturn($projects);

    $this->command->setGitLabClient($gitlab_client->reveal());

    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper->checkRequiredBinariesExist(['git']);
    $process = $this->mockProcess();
    $local_machine_helper->execute(Argument::containing('remote'), Argument::type('callable'), '/home/ide/project', FALSE)->willReturn($process->reveal());
    $local_machine_helper->execute(Argument::containing('push'), Argument::type('callable'), '/home/ide/project', FALSE)->willReturn($process->reveal());

    $this->mockGetCurrentBranchName($local_machine_helper);
    $this->mockGitlabGetHost($local_machine_helper, $this->gitLabHost);
    $this->mockGitlabGetToken($local_machine_helper, $this->gitLabToken, $this->gitLabToken, $this->gitLabToken);

    /** @var Filesystem|ObjectProphecy $file_system */
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $local_machine_helper->getFilesystem()->willReturn($file_system->reveal())->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    // Set properties and execute.
    $this->executeCommand($args, $inputs);

    // Assertions.
    $this->assertEquals(0, $this->getStatusCode());
  }

  public function testInvalidGitLabCredentials() {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $gitlab_client = $this->mockGitLabAuthenticate($local_machine_helper, $this->gitLabHost, $this->gitLabToken);
    $this->command->setGitLabClient($gitlab_client->reveal());
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    try {
      $this->executeCommand([
        '--key' => $this->key,
        '--secret' => $this->secret,
      ], []);
    }
    catch (RuntimeException $exception) {
      $this->assertStringContainsString('Unable to authenticate with Code Studio', $this->getDisplay());
    }
    $this->assertEquals(1, $this->getStatusCode());
  }

  public function testMissingGitLabCredentials() {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockGitlabGetHost($local_machine_helper, $this->gitLabHost);
    $this->mockGitlabGetToken($local_machine_helper, $this->gitLabToken, $this->gitLabHost, FALSE);
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    try {
      $this->executeCommand([
        '--key' => $this->key,
        '--secret' => $this->secret,
      ], []);
    }
    catch (AcquiaCliException $exception) {
    }
    $this->assertStringContainsString('You must first authenticate with Code Studio', $this->getDisplay());
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $projects
   */
  protected function mockGitLabProjectsTokens(ObjectProphecy $projects): void {
    $tokens = [
      0 =>
        [
          'id' => $this->gitLabTokenId,
          'name' => 'acquia-codestudio',
          'revoked' => FALSE,
          'created_at' => '2021-12-28T20:08:21.629Z',
          'scopes' =>
            [
              0 => 'api',
              1 => 'write_repository',
            ],
          'user_id' => 154,
          'active' => TRUE,
          'expires_at' => NULL,
          'access_level' => 40,
        ],
    ];
    $projects->projectAccessTokens($this->gitLabProjectId)->willReturn($tokens);
    $projects->deleteProjectAccessToken($this->gitLabProjectId, $this->gitLabTokenId);
    $token = $tokens[0];
    $token['token'] = 'token';
    $projects->createProjectAccessToken($this->gitLabProjectId, Argument::type('array'))->willReturn($token);
  }

  /**
   * @param $local_machine_helper
   */
  protected function mockGetCurrentBranchName($local_machine_helper): void {
    $process = $this->mockProcess();
    $process->getOutput()->willReturn('main');
    $local_machine_helper->execute([
      'git',
      'rev-parse',
      '--abbrev-ref',
      'HEAD',
    ], NULL, NULL, FALSE)->willReturn($process->reveal());
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $gitlab_client
   */
  protected function mockGitLabGroups(ObjectProphecy $gitlab_client): void {
    $groups = $this->prophet->prophesize(Groups::class);
    $groups->all(Argument::type('array'))->willReturn([
      0 =>
        [
          'id' => 47,
          'web_url' => 'https://code.cloudservices.acquia.io/groups/awesome-demo',
          'name' => 'awesome-demo',
          'path' => 'awesome-demo',
          'description' => '',
          'visibility' => 'private',
          'share_with_group_lock' => FALSE,
          'require_two_factor_authentication' => FALSE,
          'two_factor_grace_period' => 48,
          'project_creation_level' => 'developer',
          'auto_devops_enabled' => NULL,
          'subgroup_creation_level' => 'maintainer',
          'emails_disabled' => NULL,
          'mentions_disabled' => NULL,
          'lfs_enabled' => TRUE,
          'default_branch_protection' => 2,
          'avatar_url' => NULL,
          'request_access_enabled' => TRUE,
          'full_name' => 'awesome-demo',
          'full_path' => 'awesome-demo',
          'created_at' => '2021-11-16T18:54:31.275Z',
          'parent_id' => NULL,
          'ldap_cn' => NULL,
          'ldap_access' => NULL,
          'marked_for_deletion_on' => NULL,
        ],
      1 =>
        [
          'id' => 68,
          'web_url' => 'https://code.cloudservices.acquia.io/groups/nestle',
          'name' => 'Nestle',
          'path' => 'nestle',
          'description' => '',
          'visibility' => 'private',
          'share_with_group_lock' => FALSE,
          'require_two_factor_authentication' => FALSE,
          'two_factor_grace_period' => 48,
          'project_creation_level' => 'developer',
          'auto_devops_enabled' => NULL,
          'subgroup_creation_level' => 'maintainer',
          'emails_disabled' => NULL,
          'mentions_disabled' => NULL,
          'lfs_enabled' => TRUE,
          'default_branch_protection' => 2,
          'avatar_url' => NULL,
          'request_access_enabled' => TRUE,
          'full_name' => 'Nestle',
          'full_path' => 'nestle',
          'created_at' => '2021-12-14T18:49:50.724Z',
          'parent_id' => NULL,
          'ldap_cn' => NULL,
          'ldap_access' => NULL,
          'marked_for_deletion_on' => NULL,
        ],
    ]);
    $gitlab_client->groups()->willReturn($groups->reveal());
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $gitlab_client
   */
  protected function mockGitLabNamespaces(ObjectProphecy $gitlab_client): void {
    $namespaces = $this->prophet->prophesize(ProjectNamespaces::class);
    $namespaces->show(Argument::type('string'))->willReturn([
      'id' => 48,
      'name' => 'Matthew Grasmick',
      'path' => 'matthew.grasmick',
      'kind' => 'user',
      'full_path' => 'matthew.grasmick',
      'parent_id' => NULL,
      'avatar_url' => 'https://secure.gravatar.com/avatar/5ee7b8ad954bf7156e6eb57a45d60dec?s=80&d=identicon',
      'web_url' => 'https://code.cloudservices.acquia.io/matthew.grasmick',
      'billable_members_count' => 1,
      'seats_in_use' => 0,
      'max_seats_used' => 0,
      'plan' => 'default',
      'trial_ends_on' => NULL,
      'trial' => FALSE,
    ]);
    $gitlab_client->namespaces()->willReturn($namespaces->reveal());
  }

  /**
   * @param $gitlab_project_id
   * @param \Prophecy\Prophecy\ObjectProphecy $projects
   */
  protected function mockGitLabVariables($gitlab_project_id, ObjectProphecy $projects): void {
    $projects->variables($gitlab_project_id)->willReturn($this->getMockGitLabVariables());
    $projects->addVariable($gitlab_project_id, Argument::type('string'), Argument::type('string'), Argument::type('bool'), NULL, Argument::type('array'));
  }

}
