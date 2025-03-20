<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioPhpVersionCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\CommandTestBase;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Prophecy\Argument;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property \Acquia\Cli\Command\CodeStudio\CodeStudioPhpVersionCommand $command
 */
class CodeStudioPhpVersionCommandTest extends CommandTestBase
{
    private string $gitLabHost = 'gitlabhost';

    private string $gitLabToken = 'gitlabtoken';

    private static int $gitLabProjectId = 33;

    public static string $applicationUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(CodeStudioPhpVersionCommand::class);
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestPhpVersionFailure(): array
    {
        return [
            ['', ValidatorException::class],
            ['8', ValidatorException::class],
            ['8 1', ValidatorException::class],
            ['ABC', ValidatorException::class],
        ];
    }

    /**
     * Test for the wrong PHP version passed as argument.
     *
     * @dataProvider providerTestPhpVersionFailure
     */
    public function testPhpVersionFailure(mixed $phpVersion): void
    {
        $this->expectException(ValidatorException::class);
        $this->executeCommand([
            'applicationUuid' => self::$applicationUuid,
            'php-version' => $phpVersion,
        ]);
    }

    /**
     * Test for CI/CD not enabled on the project.
     */
    public function testCiCdNotEnabled(): void
    {
        $this->mockApplicationRequest();
        $gitlabClient = $this->prophet->prophesize(Client::class);
        $this->mockGitLabUsersMe($gitlabClient);
        $projects = $this->mockGetGitLabProjects(
            self::$applicationUuid,
            self::$gitLabProjectId,
            [self::getMockedGitLabProject(self::$gitLabProjectId)],
        );

        $gitlabClient->projects()->willReturn($projects);

        $this->command->setGitLabClient($gitlabClient->reveal());
        $this->executeCommand([
            '--gitlab-host-name' => $this->gitLabHost,
            '--gitlab-token' => $this->gitLabToken,
            'applicationUuid' => self::$applicationUuid,
            'php-version' => '8.1',
        ]);

        $output = $this->getDisplay();
        $this->assertStringContainsString('CI/CD is not enabled for this application in code studio.', $output);
    }

    /**
     * Test for failed PHP version add.
     */
    public function testPhpVersionAddFail(): void
    {
        $this->mockApplicationRequest();
        $gitlabClient = $this->prophet->prophesize(Client::class);
        $this->mockGitLabUsersMe($gitlabClient);
        $mockedProject = self::getMockedGitLabProject(self::$gitLabProjectId);
        $mockedProject['jobs_enabled'] = true;
        $projects = $this->mockGetGitLabProjects(
            self::$applicationUuid,
            self::$gitLabProjectId,
            [$mockedProject],
        );

        $projects->variables(self::$gitLabProjectId)
            ->willReturn($this->getMockGitLabVariables());
        $projects->addVariable(self::$gitLabProjectId, Argument::type('string'), Argument::type('string'))
            ->willReturn(true)
            ->willThrow(RuntimeException::class);

        $gitlabClient->projects()->willReturn($projects);
        $this->command->setGitLabClient($gitlabClient->reveal());
        $this->executeCommand([
            '--gitlab-host-name' => $this->gitLabHost,
            '--gitlab-token' => $this->gitLabToken,
            'applicationUuid' => self::$applicationUuid,
            'php-version' => '8.1',
        ]);

        $output = $this->getDisplay();
        $this->assertStringContainsString('Unable to update the PHP version to 8.1', $output);
    }

    /**
     * @group brokenProphecy
     */
    public function testPhpVersionAdd(): void
    {
        $this->mockApplicationRequest();
        $gitlabClient = $this->prophet->prophesize(Client::class);
        $this->mockGitLabUsersMe($gitlabClient);
        $mockedProject = self::getMockedGitLabProject(self::$gitLabProjectId);
        $mockedProject['jobs_enabled'] = true;
        $projects = $this->mockGetGitLabProjects(
            self::$applicationUuid,
            self::$gitLabProjectId,
            [$mockedProject],
        );

        $projects->variables(self::$gitLabProjectId)
            ->willReturn($this->getMockGitLabVariables());
        $projects->addVariable(self::$gitLabProjectId, Argument::type('string'), Argument::type('string'))->willReturn(true);

        $gitlabClient->projects()->willReturn($projects);

        $this->command->setGitLabClient($gitlabClient->reveal());
        $this->executeCommand([
            '--gitlab-host-name' => $this->gitLabHost,
            '--gitlab-token' => $this->gitLabToken,
            'applicationUuid' => self::$applicationUuid,
            'php-version' => '8.1',
        ]);

        $output = $this->getDisplay();
        $this->assertStringContainsString('PHP version is updated to 8.1 successfully!', $output);
    }

    /**
     * Test for failed PHP version update.
     */
    public function testPhpVersionUpdateFail(): void
    {
        $this->mockApplicationRequest();
        $gitlabClient = $this->prophet->prophesize(Client::class);
        $this->mockGitLabUsersMe($gitlabClient);
        $mockedProject = self::getMockedGitLabProject(self::$gitLabProjectId);
        $mockedProject['jobs_enabled'] = true;
        $projects = $this->mockGetGitLabProjects(
            self::$applicationUuid,
            self::$gitLabProjectId,
            [$mockedProject],
        );

        $variables = $this->getMockGitLabVariables();
        $variables[] = [
            'environment_scope' => '*',
            'key' => 'PHP_VERSION',
            'masked' => false,
            'protected' => false,
            'value' => '8.1',
            'variable_type' => 'env_var',
        ];
        $projects->variables(self::$gitLabProjectId)->willReturn($variables);
        $projects->updateVariable(self::$gitLabProjectId, Argument::type('string'), Argument::type('string'))
            ->willThrow(RuntimeException::class);

        $gitlabClient->projects()->willReturn($projects);
        $this->command->setGitLabClient($gitlabClient->reveal());
        $this->executeCommand([
            '--gitlab-host-name' => $this->gitLabHost,
            '--gitlab-token' => $this->gitLabToken,
            'applicationUuid' => self::$applicationUuid,
            'php-version' => '8.1',
        ]);

        $output = $this->getDisplay();
        $this->assertStringContainsString('Unable to update the PHP version to 8.1', $output);
    }

    /**
     * @group brokenProphecy
     */
    public function testPhpVersionUpdate(): void
    {
        $this->mockApplicationRequest();
        $gitlabClient = $this->prophet->prophesize(Client::class);
        $this->mockGitLabUsersMe($gitlabClient);
        $mockedProject = self::getMockedGitLabProject(self::$gitLabProjectId);
        $mockedProject['jobs_enabled'] = true;
        $projects = $this->mockGetGitLabProjects(
            self::$applicationUuid,
            self::$gitLabProjectId,
            [$mockedProject],
        );

        $variables = $this->getMockGitLabVariables();
        $variables[] = [
            'environment_scope' => '*',
            'key' => 'PHP_VERSION',
            'masked' => false,
            'protected' => false,
            'value' => '8.1',
            'variable_type' => 'env_var',
        ];
        $projects->variables(self::$gitLabProjectId)->willReturn($variables);
        $projects->updateVariable(self::$gitLabProjectId, Argument::type('string'), Argument::type('string'))->willReturn(true);

        $gitlabClient->projects()->willReturn($projects);

        $this->command->setGitLabClient($gitlabClient->reveal());
        $this->executeCommand([
            '--gitlab-host-name' => $this->gitLabHost,
            '--gitlab-token' => $this->gitLabToken,
            'applicationUuid' => self::$applicationUuid,
            'php-version' => '8.1',
        ]);

        $output = $this->getDisplay();
        $this->assertStringContainsString('PHP version is updated to 8.1 successfully!', $output);
    }
}
