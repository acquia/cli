<?php

declare(strict_types = 1);

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
class CodeStudioWizardCommandTest extends WizardTestBase {

  use IdeRequiredTestTrait;

  private string $gitLabHost = 'gitlabhost';
  private string $gitLabToken = 'gitlabtoken';
  private string $ciPath = 'ciPath';

  private int $gitLabProjectId = 33;
  private int $gitLabTokenId = 118;

  public function setUp(): void {
    parent::setUp();
    $this->mockApplicationRequest();
    TestBase::setEnvVars(['GITLAB_HOST' => 'code.cloudservices.acquia.io']);
  }

  public function tearDown(): void {
    parent::tearDown();
    TestBase::unsetEnvVars(['GITLAB_HOST' => 'code.cloudservices.acquia.io']);
  }

  protected function createCommand(): CommandBase {
    return $this->injectCommand(CodeStudioWizardCommand::class);
  }

  /**
   * @return array<mixed>
   */
  public function providerTestCommand(): array {
    return [
      [
        // One project.
        [$this->getMockedGitLabProject($this->gitLabProjectId)],
        // Inputs.
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
          // Found multiple projects that could match the Sample application 1 application. Choose which one to configure.
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
          // Select a project type Node_project.
          '1',
          // Select NODE version 18.17.1.
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
          // Select a project type Node_project.
          '1',
          // Select NODE version 20.5.1.
          '1',
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
          'n',
          // Choose project.
          '0',
          // Do you want to continue?
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
          // Enter Cloud Key.
          $this->key,
          // Enter Cloud secret,.
          $this->secret,
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
          // 'Would you like to create a new Code Studio project?
          'y',
          // Enter Cloud Key.
          $this->key,
          // Enter Cloud secret,.
          $this->secret,
          // Select a project type Node_project.
          '1',
          // Select NODE version 18.17.1.
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
          // 'Would you like to create a new Code Studio project?
          'y',
          // Enter Cloud Key.
          $this->key,
          // Enter Cloud secret,.
          $this->secret,
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
          // 'Would you like to create a new Code Studio project?
          'y',
          // Enter Cloud Key.
          $this->key,
          // Enter Cloud secret,.
          $this->secret,
          // Select a project type Node_project.
          '1',
          // Select NODE version 20.5.1.
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
  public function testCommand(array $mockedGitlabProjects, array $inputs, array $args): void {
    $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))->shouldBeCalled();
    $this->mockRequest('getAccount');
    $this->mockGitLabPermissionsRequest($this::$applicationUuid);

    $gitlabClient = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlabClient);
    $this->mockGitLabGroups($gitlabClient);
    $this->mockGitLabNamespaces($gitlabClient);

    $projects = $this->mockGetGitLabProjects($this::$applicationUuid, $this->gitLabProjectId, $mockedGitlabProjects);
    $parameters = [
      'container_registry_access_level' => 'disabled',
      'description' => 'Source repository for Acquia Cloud Platform application <comment>a47ac10b-58cc-4372-a567-0e02b2c3d470</comment>',
      'namespace_id' => 47,
      'topics' => 'Acquia Cloud Application',
    ];
    $projects->create('Sample-application-1', $parameters)->willReturn($this->getMockedGitLabProject($this->gitLabProjectId));
    $this->mockGitLabProjectsTokens($projects);
    $parameters = [
      'container_registry_access_level' => 'disabled',
      'description' => 'Source repository for Acquia Cloud Platform application <comment>a47ac10b-58cc-4372-a567-0e02b2c3d470</comment>',
      'topics' => 'Acquia Cloud Application',
    ];
    $projects->update($this->gitLabProjectId, $parameters)->shouldBeCalled();
    $projects->uploadAvatar(
      33,
      Argument::type('string'),
    )->shouldBeCalled();
    $this->mockGitLabVariables($this->gitLabProjectId, $projects);
    $schedules = $this->prophet->prophesize(Schedules::class);
    $schedules->showAll($this->gitLabProjectId)->willReturn([]);
    $pipeline = ['id' => 1];
    $parameters = [
      // Every Thursday at midnight.
      'cron' => '0 0 * * 4',
      'description' => 'Code Studio Automatic Updates',
      'ref' => 'master',
    ];
    $schedules->create($this->gitLabProjectId, $parameters)->willReturn($pipeline);
    $schedules->addVariable($this->gitLabProjectId, $pipeline['id'], [
      'key' => 'ACQUIA_JOBS_DEPRECATED_UPDATE',
      'value' => 'true',
    ])->shouldBeCalled();
    $schedules->addVariable($this->gitLabProjectId, $pipeline['id'], [
      'key' => 'ACQUIA_JOBS_COMPOSER_UPDATE',
      'value' => 'true',
    ])->shouldBeCalled();
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
    // Set properties and execute.
    $this->executeCommand($args, $inputs);
    $output = $this->getDisplay();
    $output_strings = $this->getOutputStrings();
    foreach ($output_strings as $output_string) {
      self::assertStringContainsString($output_string, $output);
    }

    // Assertions.
    $curlCommand = $this->command->getCurlCommand($this->gitLabToken, $this->gitLabHost, $this->gitLabProjectId, $this->ciPath);
    $curlString = $this->getCurlString();
    self::assertStringContainsString($curlString, $curlCommand);
    $this->assertEquals(0, $this->getStatusCode());
  }

  /**
   * @group brokenProphecy
   */
  public function testInvalidGitLabCredentials(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteGlabExists($localMachineHelper);
    $gitlabClient = $this->mockGitLabAuthenticate($localMachineHelper, $this->gitLabHost, $this->gitLabToken);
    $this->command->setGitLabClient($gitlabClient->reveal());

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Unable to authenticate with Code Studio');
    $this->executeCommand([
      '--key' => $this->key,
      '--secret' => $this->secret,
    ]);
  }

  /**
   * @group brokenProphecy
   */
  public function testMissingGitLabCredentials(): void {
    $localMachineHelper = $this->mockLocalMachineHelper();
    $this->mockExecuteGlabExists($localMachineHelper);
    $this->mockGitlabGetHost($localMachineHelper, $this->gitLabHost);
    $this->mockGitlabGetToken($localMachineHelper, $this->gitLabToken, $this->gitLabHost, FALSE);

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
          'expires_at' => new DateTime('+365 days'),
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
    $projects->projectAccessTokens($this->gitLabProjectId)->willReturn($tokens)->shouldBeCalled();
    $projects->deleteProjectAccessToken($this->gitLabProjectId, $this->gitLabTokenId)->shouldBeCalled();
    $token = $tokens[0];
    $token['token'] = 'token';
    $projects->createProjectAccessToken($this->gitLabProjectId, Argument::type('array'))->willReturn($token);
  }

  protected function mockGetCurrentBranchName(mixed $localMachineHelper): void {
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

  protected function mockGitLabVariables(int $gitlabProjectId, ObjectProphecy $projects): void {
    $variables = $this->getMockGitLabVariables();
    $projects->variables($gitlabProjectId)->willReturn($variables);
    foreach ($variables as $variable) {
      $projects->addVariable($gitlabProjectId, Argument::type('string'), Argument::type('string'), Argument::type('bool'), NULL, ['masked' => $variable['masked'], 'variable_type' => $variable['variable_type']])->shouldBeCalled();
    }
    foreach ($variables as $variable) {
      $projects->updateVariable($this->gitLabProjectId, $variable['key'], $variable['value'], FALSE, NULL, ['masked' => TRUE, 'variable_type' => 'env_var'])->shouldBeCalled();
    }
  }

}
