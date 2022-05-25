<?php

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioCommandTrait;
use Acquia\Cli\Command\CodeStudio\CodeStudioPipelinesMigrateCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;
use Gitlab\Client;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class CodeStudioWizardCommandTest.
 *
 * @property \Acquia\Cli\Command\CodeStudio\CodeStudioPipelinesMigrateCommand $command
 * @package Acquia\Cli\Tests\Commands
 *
 * @requires OS linux|darwin
 */
class CodeStudioPipelinesMigrateCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  private $gitLabHost = 'gitlabhost';
  private $gitLabToken = 'gitlabtoken';
  private $gitLabProjectId = 33;
  private $gitLabTokenId = 118;
  public static string $application_uuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

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
    return $this->injectCommand(CodeStudioPipelinesMigrateCommand::class);
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
          // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
          'n',
          // @todo
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
    ];
  }

  /**
   * @dataProvider providerTestCommand
   *
   * @param $mocked_gitlab_projects
   * @param $args
   * @param $inputs
   *
   * @throws \Psr\Cache\InvalidArgumentException|\Exception
   */
  public function testCommand($mocked_gitlab_projects, $inputs, $args) {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockGitlabGetHost($local_machine_helper, $this->gitLabHost);
    $this->mockGitlabGetToken($local_machine_helper, $this->gitLabToken, $this->gitLabHost);
    $gitlab_client = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlab_client);
    $this->mockAccountRequest();
    $this->mockGitLabPermissionsRequest($this::$application_uuid);
    $projects = $this->mockGetGitLabProjects($this::$application_uuid, $this->gitLabProjectId, $mocked_gitlab_projects);
    $projects->variables($this->gitLabProjectId)->willReturn(CodeStudioCommandTrait::getGitLabCiCdVariableDefaults(NULL, NULL, NULL, NULL, NULL));
    $projects->update($this->gitLabProjectId, Argument::type('array'));
    $gitlab_client->projects()->willReturn($projects);
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $file_system->exists(Argument::containingString('acquia-pipelines'))->willReturn(TRUE);
    $local_machine_helper->getFilesystem()->willReturn($file_system->reveal())->shouldBeCalled();
    $this->command->setGitLabClient($gitlab_client->reveal());
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->mockApplicationsRequest();
    // Set properties and execute.
    $this->executeCommand($args, $inputs);

    // Assertions.
    $this->assertEquals(0, $this->getStatusCode());
  }

}
