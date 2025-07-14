<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioPipelinesMigrateCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\CommandTestBase;
use Gitlab\Api\Groups;
use Gitlab\Api\ProjectNamespaces;
use Gitlab\Api\Projects;
use Gitlab\Client;
use Prophecy\Argument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * @property \Acquia\Cli\Command\CodeStudio\CodeStudioPipelinesMigrateCommand
 *     $command
 * @requires OS linux|darwin
 */
class CodeStudioPipelinesMigrateCommandTest extends CommandTestBase
{
    use IdeRequiredTestTrait;

    private string $gitLabHost = 'gitlabhost';

    private string $gitLabToken = 'gitlabtoken';

    private static int $gitLabProjectId = 33;

    public static string $applicationUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

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
        return $this->injectCommand(CodeStudioPipelinesMigrateCommand::class);
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
                    // @todo
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
                // No existing projects found - test project creation path.
                [],
                // Inputs.
                [
                    // Choose application.
                    '0',
                    // Would you like to create a new Code Studio project?
                    'y',
                    // Choose which group this new project should belong to:
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
        ];
    }

    /**
     * @dataProvider providerTestCommand
     * @param $mockedGitlabProjects
     * @param $args
     * @param $inputs
     * @group brokenProphecy
     */
    public function testCommand(mixed $mockedGitlabProjects, mixed $inputs, mixed $args): void
    {
        copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockExecuteGlabExists($localMachineHelper);
        $this->mockGitlabGetHost($localMachineHelper, $this->gitLabHost);
        $this->mockGitlabGetToken($localMachineHelper, $this->gitLabToken, $this->gitLabHost);
        $gitlabClient = $this->prophet->prophesize(Client::class);
        $this->mockGitLabUsersMe($gitlabClient);
        $this->mockRequest('getAccount');
        $this->mockGitLabPermissionsRequest($this::$applicationUuid);

        $gitlabCicdVariables = [
            [
                'key' => 'ACQUIA_APPLICATION_UUID',
                'masked' => true,
                'protected' => false,
                'value' => null,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
                'masked' => true,
                'protected' => false,
                'value' => null,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_CLOUD_API_TOKEN_SECRET',
                'masked' => true,
                'protected' => false,
                'value' => null,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_GLAB_TOKEN_NAME',
                'masked' => true,
                'protected' => false,
                'value' => null,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_GLAB_TOKEN_SECRET',
                'masked' => true,
                'protected' => false,
                'value' => null,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'MYSQL_VERSION',
                'masked' => false,
                'protected' => false,
                'value' => null,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'PHP_VERSION',
                'masked' => false,
                'protected' => false,
                'value' => null,
                'variable_type' => 'env_var',
            ],
        ];

        // Handle project creation test case.
        if (empty($mockedGitlabProjects)) {
            // Mock empty search results to trigger project creation path.
            $projects = $this->prophet->prophesize(Projects::class);
            $projects->all(['search' => $this::$applicationUuid])
                ->willReturn([]);

            // Mock groups for project creation (return both group and namespace)
            $groups = $this->prophet->prophesize(Groups::class);
            $groups->all(['all_available' => true, 'min_access_level' => 40])
                ->willReturn([
                    ['id' => 1, 'path' => 'test-group'],
                    // Namespace as a group.
                    ['id' => 2, 'path' => 'matthew.grasmick'],
                ]);
            $gitlabClient->groups()->willReturn($groups->reveal());

            // Mock namespaces for project creation.
            $namespaces = $this->prophet->prophesize(ProjectNamespaces::class);
            $namespaces->show('matthew.grasmick')
                ->willReturn(['id' => 2, 'path' => 'matthew.grasmick']);
            $gitlabClient->namespaces()->willReturn($namespaces->reveal());

            // Mock project creation with verification that description is set.
            $projects->create(Argument::type('string'), Argument::that(function ($params) {
                // Verify that description contains the application UUID.
                $this->assertArrayHasKey('description', $params);
                $this->assertStringContainsString($this::$applicationUuid, $params['description']);
                return true;
            }))->willReturn(self::getMockedGitLabProject(self::$gitLabProjectId));

            // Mock avatar upload (can fail)
            $projects->uploadAvatar(self::$gitLabProjectId, Argument::type('string'))
                ->willThrow(new \Gitlab\Exception\ValidationFailedException('Failed to upload avatar'));

            // Mock variables for CI/CD check.
            $projects->variables(self::$gitLabProjectId)
                ->willReturn($gitlabCicdVariables);
            $projects->update(self::$gitLabProjectId, Argument::any())->willReturn(true);
        } else {
            $projects = $this->mockGetGitLabProjects($this::$applicationUuid, self::$gitLabProjectId, $mockedGitlabProjects);
            $projects->variables(self::$gitLabProjectId)
                ->willReturn($gitlabCicdVariables);
            $projects->update(self::$gitLabProjectId, Argument::any())->willReturn(true);
        }
        $gitlabClient->projects()->willReturn($projects);
        $localMachineHelper->getFilesystem()
            ->willReturn(new Filesystem())
            ->shouldBeCalled();
        $this->command->setGitLabClient($gitlabClient->reveal());

        $this->mockRequest('getApplications');
        // Set properties and execute.
        $this->executeCommand($args, $inputs);

        // Assertions.
        $this->assertEquals(0, $this->getStatusCode());
        $gitlabCiYmlFilePath = $this->projectDir . '/.gitlab-ci.yml';
        $this->assertFileExists($gitlabCiYmlFilePath);
        // @todo Assert things about skips. Composer install, BLT, launch_ode.
        $contents = Yaml::parseFile($gitlabCiYmlFilePath);
        $arraySkipMap = ['composer install', '${BLT_DIR}', 'launch_ode'];
        foreach ($contents as $values) {
            if (array_key_exists('script', $values)) {
                foreach ($arraySkipMap as $map) {
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
