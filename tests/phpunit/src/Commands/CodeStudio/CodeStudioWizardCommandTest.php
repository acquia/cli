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
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @property \Acquia\Cli\Command\CodeStudio\CodeStudioWizardCommand $command
 * @requires OS linux|darwin
 */
class CodeStudioWizardCommandTest extends WizardTestBase {

  use IdeRequiredTestTrait;

  private string $gitLabHost = 'gitlabhost';
  private string $gitLabToken = 'gitlabtoken';

  private int $gitLabProjectId = 33;
  private int $gitLabTokenId = 118;

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->mockApplicationRequest();
    TestBase::setEnvVars(['GITLAB_HOST' => 'code.cloudservices.acquia.io']);
  }

  public function tearDown(): void {
    parent::tearDown();
    TestBase::unsetEnvVars(['GITLAB_HOST' => 'code.cloudservices.acquia.io']);
  }

  protected function createCommand(): Command {
    return $this->injectCommand(CodeStudioWizardCommand::class);
  }

  /**
   * @return array
   */
  public function providerTestCommand(): array {
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
        ],
      ],
      // Two projects.
      [
        [$this->getMockedGitLabProject($this->gitLabProjectId), $this->getMockedGitLabProject($this->gitLabProjectId)],
        // Inputs.
        [
          //  Found multiple projects that could match the Sample application 1 application. Choose which one to configure.
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
        ],
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
        ],
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
        ],
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
        [],
      ],
    ];
  }

  /**
   * @dataProvider providerTestCommand
   * @param $mockedGitlabProjects
   * @param $args
   * @param $inputs
   */
  public function testCommand($mockedGitlabProjects, $inputs, $args): void {
    $environmentsResponse = $this->getMockEnvironmentsResponse();
    $selectedEnvironment = $environmentsResponse->_embedded->items[0];
    $this->clientProphecy->request('get', "/applications/{$this::$applicationUuid}/environments")->willReturn($environmentsResponse->_embedded->items)->shouldBeCalled();
    $this->mockRequest('/account');
    $this->mockGitLabPermissionsRequest($this::$applicationUuid);

    $gitlabClient = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlabClient);
    $this->mockGitLabGroups($gitlabClient);
    $this->mockGitLabNamespaces($gitlabClient);

    $projects = $this->mockGetGitLabProjects($this::$applicationUuid, $this->gitLabProjectId, $mockedGitlabProjects);
    $projects->create(Argument::type('string'), Argument::type('array'))->willReturn($this->getMockedGitLabProject($this->gitLabProjectId));
    $this->mockGitLabProjectsTokens($projects);
    $projects->update($this->gitLabProjectId, Argument::type('array'));
    $this->mockGitLabVariables($this->gitLabProjectId, $projects);
    $schedules = $this->prophet->prophesize(Schedules::class);
    $schedules->showAll($this->gitLabProjectId)->willReturn([]);
    $pipeline = ['id' => 1];
    $schedules->create($this->gitLabProjectId, Argument::type('array'))->willReturn($pipeline);
    $schedules->addVariable($this->gitLabProjectId, $pipeline['id'], Argument::type('array'));
    $gitlabClient->schedules()->willReturn($schedules->reveal());
    $gitlabClient->projects()->willReturn($projects);

    $this->command->setGitLabClient($gitlabClient->reveal());

    $localMachineHelper = $this->mockLocalMachineHelper();
    $localMachineHelper->checkRequiredBinariesExist(['git']);
    $this->mockExecuteGlabExists($localMachineHelper);
    $process = $this->mockProcess();
    $localMachineHelper->execute(Argument::containing('remote'), Argument::type('callable'), '/home/ide/project', FALSE)->willReturn($process->reveal());
    $localMachineHelper->execute(Argument::containing('push'), Argument::type('callable'), '/home/ide/project', FALSE)->willReturn($process->reveal());

    $this->mockGetCurrentBranchName($localMachineHelper);
    $this->mockGitlabGetHost($localMachineHelper, $this->gitLabHost);
    $this->mockGitlabGetToken($localMachineHelper, $this->gitLabToken, $this->gitLabHost);

    /** @var Filesystem|ObjectProphecy $fileSystem */
    $fileSystem = $this->prophet->prophesize(Filesystem::class);
    $localMachineHelper->getFilesystem()->willReturn($fileSystem->reveal())->shouldBeCalled();
    $this->command->localMachineHelper = $localMachineHelper->reveal();

    // Set properties and execute.
    $this->executeCommand($args, $inputs);

    // Assertions.
    $this->assertEquals(0, $this->getStatusCode());
  }

  public function testInvalidGitLabCredentials(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteGlabExists($localMachineHelper);
    $gitlabClient = $this->mockGitLabAuthenticate($localMachineHelper, $this->gitLabHost, $this->gitLabToken);
    $this->command->setGitLabClient($gitlabClient->reveal());
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Unable to authenticate with Code Studio');
    $this->executeCommand([
      '--key' => $this->key,
      '--secret' => $this->secret,
    ]);
  }

  public function testMissingGitLabCredentials(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteGlabExists($localMachineHelper);
    $this->mockGitlabGetHost($localMachineHelper, $this->gitLabHost);
    $this->mockGitlabGetToken($localMachineHelper, $this->gitLabToken, $this->gitLabHost, FALSE);
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Could not determine GitLab token');
    $this->executeCommand([
      '--key' => $this->key,
      '--secret' => $this->secret,
    ]);
  }

  protected function mockGitLabProjectsTokens(ObjectProphecy $projects): void {
    $tokens = [
      0 => [
          'access_level' => 40,
          'active' => TRUE,
          'created_at' => '2021-12-28T20:08:21.629Z',
          'expires_at' => NULL,
          'id' => $this->gitLabTokenId,
          'name' => 'acquia-codestudio',
          'revoked' => FALSE,
          'scopes' => [
              0 => 'api',
              1 => 'write_repository',
            ],
          'user_id' => 154,
        ],
    ];
    $projects->projectAccessTokens($this->gitLabProjectId)->willReturn($tokens);
    $projects->deleteProjectAccessToken($this->gitLabProjectId, $this->gitLabTokenId);
    $token = $tokens[0];
    $token['token'] = 'token';
    $projects->createProjectAccessToken($this->gitLabProjectId, Argument::type('array'))->willReturn($token);
  }

  /**
   * @param $localMachineHelper
   */
  protected function mockGetCurrentBranchName($localMachineHelper): void {
    $process = $this->mockProcess();
    $process->getOutput()->willReturn('main');
    $localMachineHelper->execute([
      'git',
      'rev-parse',
      '--abbrev-ref',
      'HEAD',
    ], NULL, NULL, FALSE)->willReturn($process->reveal());
  }

  protected function mockGitLabGroups(ObjectProphecy $gitlabClient): void {
    $groups = $this->prophet->prophesize(Groups::class);
    $groups->all(Argument::type('array'))->willReturn([
      0 => [
          'auto_devops_enabled' => NULL,
          'avatar_url' => NULL,
          'created_at' => '2021-11-16T18:54:31.275Z',
          'default_branch_protection' => 2,
          'description' => '',
          'emails_disabled' => NULL,
          'full_name' => 'awesome-demo',
          'full_path' => 'awesome-demo',
          'id' => 47,
          'ldap_access' => NULL,
          'ldap_cn' => NULL,
          'lfs_enabled' => TRUE,
          'marked_for_deletion_on' => NULL,
          'mentions_disabled' => NULL,
          'name' => 'awesome-demo',
          'parent_id' => NULL,
          'path' => 'awesome-demo',
          'project_creation_level' => 'developer',
          'request_access_enabled' => TRUE,
          'require_two_factor_authentication' => FALSE,
          'share_with_group_lock' => FALSE,
          'subgroup_creation_level' => 'maintainer',
          'two_factor_grace_period' => 48,
          'visibility' => 'private',
          'web_url' => 'https://code.cloudservices.acquia.io/groups/awesome-demo',
        ],
      1 => [
          'auto_devops_enabled' => NULL,
          'avatar_url' => NULL,
          'created_at' => '2021-12-14T18:49:50.724Z',
          'default_branch_protection' => 2,
          'description' => '',
          'emails_disabled' => NULL,
          'full_name' => 'Nestle',
          'full_path' => 'nestle',
          'id' => 68,
          'ldap_access' => NULL,
          'ldap_cn' => NULL,
          'lfs_enabled' => TRUE,
          'marked_for_deletion_on' => NULL,
          'mentions_disabled' => NULL,
          'name' => 'Nestle',
          'parent_id' => NULL,
          'path' => 'nestle',
          'project_creation_level' => 'developer',
          'request_access_enabled' => TRUE,
          'require_two_factor_authentication' => FALSE,
          'share_with_group_lock' => FALSE,
          'subgroup_creation_level' => 'maintainer',
          'two_factor_grace_period' => 48,
          'visibility' => 'private',
          'web_url' => 'https://code.cloudservices.acquia.io/groups/nestle',
        ],
    ]);
    $gitlabClient->groups()->willReturn($groups->reveal());
  }

  protected function mockGitLabNamespaces(ObjectProphecy $gitlabClient): void {
    $namespaces = $this->prophet->prophesize(ProjectNamespaces::class);
    $namespaces->show(Argument::type('string'))->willReturn([
      'avatar_url' => 'https://secure.gravatar.com/avatar/5ee7b8ad954bf7156e6eb57a45d60dec?s=80&d=identicon',
      'billable_members_count' => 1,
      'full_path' => 'matthew.grasmick',
      'id' => 48,
      'kind' => 'user',
      'max_seats_used' => 0,
      'name' => 'Matthew Grasmick',
      'parent_id' => NULL,
      'path' => 'matthew.grasmick',
      'plan' => 'default',
      'seats_in_use' => 0,
      'trial' => FALSE,
      'trial_ends_on' => NULL,
      'web_url' => 'https://code.cloudservices.acquia.io/matthew.grasmick',
    ]);
    $gitlabClient->namespaces()->willReturn($namespaces->reveal());
  }

  /**
   * @param $gitlabProjectId
   */
  protected function mockGitLabVariables($gitlabProjectId, ObjectProphecy $projects): void {
    $projects->variables($gitlabProjectId)->willReturn($this->getMockGitLabVariables());
    $projects->addVariable($gitlabProjectId, Argument::type('string'), Argument::type('string'), Argument::type('bool'), NULL, Argument::type('array'));
  }

}
