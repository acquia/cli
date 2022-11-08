<?php

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioCiCdVariables;
use Acquia\Cli\Command\CodeStudio\CodeStudioPipelinesMigrateCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;
use Gitlab\Client;
use org\bovigo\vfs\vfsStream;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

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

  private string $gitLabHost = 'gitlabhost';
  private string $gitLabToken = 'gitlabtoken';
  private int $gitLabProjectId = 33;
  private int $gitLabTokenId = 118;
  public static string $application_uuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \JsonException
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
  public function testCommand($mocked_gitlab_projects, $inputs, $args): void {
    vfsStream::newFile('acquia-pipelines.yml')->at($this->vfsProject)->withContent(file_get_contents($this->realProjectFixtureDir . '/acquia-pipelines.yml'));
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockExecuteGlabExists($local_machine_helper);
    $this->mockGitlabGetHost($local_machine_helper, $this->gitLabHost);
    $this->mockGitlabGetToken($local_machine_helper, $this->gitLabToken, $this->gitLabHost);
    $gitlab_client = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlab_client);
    $this->mockAccountRequest();
    $this->mockGitLabPermissionsRequest($this::$application_uuid);
    $projects = $this->mockGetGitLabProjects($this::$application_uuid, $this->gitLabProjectId, $mocked_gitlab_projects);
    $projects->variables($this->gitLabProjectId)->willReturn(CodeStudioCiCdVariables::getDefaults());
    $projects->update($this->gitLabProjectId, Argument::type('array'));
    $gitlab_client->projects()->willReturn($projects);
    $local_machine_helper->getFilesystem()->willReturn(new Filesystem())->shouldBeCalled();
    $this->command->setGitLabClient($gitlab_client->reveal());
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->mockApplicationsRequest();
    // Set properties and execute.
    $this->executeCommand($args, $inputs);

    // Assertions.
    $this->assertEquals(0, $this->getStatusCode());
    $gitlab_ci_yml_file_path = $this->projectDir . '/.gitlab-ci.yml';
    $this->assertFileExists($gitlab_ci_yml_file_path);
    // @todo Assert things about skips. Composer install, BLT, launch_ode.
    $contents = Yaml::parseFile($gitlab_ci_yml_file_path);
    $array_skip_map = ['composer install','${BLT_DIR}','launch_ode'];
    foreach ($contents as $values) {
      if (array_key_exists('script', $values)) {
        foreach ($array_skip_map as $map) {
          $this->assertNotContains($map, $values['script'], "Skip option found");
        }
      }
    }
    $this->assertArrayHasKey('include', $contents);
    $this->assertArrayHasKey('variables', $contents);
    $this->assertArrayHasKey('setup', $contents);
    $this->assertArrayHasKey('launch_ode', $contents);
    $this->assertArrayHasKey('script', $contents['launch_ode']);
    $this->assertNotEmpty($contents['launch_ode']['script']);
    $this->assertArrayHasKey('script', $contents['setup']);
    $this->assertArrayHasKey('stage', $contents['setup']);
    $this->assertEquals('Build Drupal', $contents['setup']['stage']);
    $this->assertArrayHasKey('needs', $contents['setup']);
    $this->assertIsArray($contents['setup']['needs']);
  }

}
