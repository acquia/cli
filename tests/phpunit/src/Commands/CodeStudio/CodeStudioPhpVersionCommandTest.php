<?php

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioPhpVersionCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property \Acquia\Cli\Command\CodeStudio\CodeStudioPhpVersionCommand $command
 */
class CodeStudioPhpVersionCommandTest extends CommandTestBase {

  private string $gitLabHost = 'gitlabhost';
  private string $gitLabToken = 'gitlabtoken';
  private int $gitLabProjectId = 33;
  public static string $application_uuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

  protected function createCommand(): Command {
    return $this->injectCommand(CodeStudioPhpVersionCommand::class);
  }

  /**
   * @return array
   */
  public function providerTestPhpVersionFailure(): array {
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
  public function testPhpVersionFailure($php_version): void {
    $this->expectException(ValidatorException::class);
    $this->executeCommand([
      'applicationUuid' => self::$application_uuid,
      'php-version' => $php_version,
    ]);
  }

  /**
   * Test for CI/CD not enabled on the project.
   */
  public function testCiCdNotEnabled(): void {
    $this->mockApplicationRequest();
    $gitlab_client = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlab_client);
    $projects = $this->mockGetGitLabProjects(
      self::$application_uuid,
      $this->gitLabProjectId,
      [$this->getMockedGitLabProject($this->gitLabProjectId)],
    );

    $gitlab_client->projects()->willReturn($projects);

    $this->command->setGitLabClient($gitlab_client->reveal());
    $this->executeCommand([
      '--gitlab-host-name' => $this->gitLabHost,
      '--gitlab-token' => $this->gitLabToken,
      'applicationUuid' => self::$application_uuid,
      'php-version' => '8.1',
    ]);

    $output = $this->getDisplay();
    $this->assertStringContainsString('CI/CD is not enabled for this application in code studio.', $output);
  }

  /**
   * Test for failed PHP version add.
   */
  public function testPhpVersionAddFail(): void {
    $this->mockApplicationRequest();
    $gitlab_client = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlab_client);
    $mocked_project = $this->getMockedGitLabProject($this->gitLabProjectId);
    $mocked_project['jobs_enabled'] = TRUE;
    $projects = $this->mockGetGitLabProjects(
      self::$application_uuid,
      $this->gitLabProjectId,
      [$mocked_project],
    );

    $projects->variables($this->gitLabProjectId)->willReturn($this->getMockGitLabVariables());
    $projects->addVariable($this->gitLabProjectId, Argument::type('string'), Argument::type('string'))
      ->willThrow(RuntimeException::class);

    $gitlab_client->projects()->willReturn($projects);
    $this->command->setGitLabClient($gitlab_client->reveal());
    $this->executeCommand([
      '--gitlab-host-name' => $this->gitLabHost,
      '--gitlab-token' => $this->gitLabToken,
      'applicationUuid' => self::$application_uuid,
      'php-version' => '8.1',
    ]);

    $output = $this->getDisplay();
    $this->assertStringContainsString('Unable to update the PHP version to 8.1', $output);
  }

  /**
   * Test for successful PHP version add.
   */
  public function testPhpVersionAdd(): void {
    $this->mockApplicationRequest();
    $gitlab_client = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlab_client);
    $mocked_project = $this->getMockedGitLabProject($this->gitLabProjectId);
    $mocked_project['jobs_enabled'] = TRUE;
    $projects = $this->mockGetGitLabProjects(
      self::$application_uuid,
      $this->gitLabProjectId,
      [$mocked_project],
    );

    $projects->variables($this->gitLabProjectId)->willReturn($this->getMockGitLabVariables());
    $projects->addVariable($this->gitLabProjectId, Argument::type('string'), Argument::type('string'));

    $gitlab_client->projects()->willReturn($projects);

    $this->command->setGitLabClient($gitlab_client->reveal());
    $this->executeCommand([
      '--gitlab-host-name' => $this->gitLabHost,
      '--gitlab-token' => $this->gitLabToken,
      'applicationUuid' => self::$application_uuid,
      'php-version' => '8.1',
    ]);

    $output = $this->getDisplay();
    $this->assertStringContainsString('PHP version is updated to 8.1 successfully!', $output);
  }

  /**
   * Test for failed PHP version update.
   */
  public function testPhpVersionUpdateFail(): void {
    $this->mockApplicationRequest();
    $gitlab_client = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlab_client);
    $mocked_project = $this->getMockedGitLabProject($this->gitLabProjectId);
    $mocked_project['jobs_enabled'] = TRUE;
    $projects = $this->mockGetGitLabProjects(
      self::$application_uuid,
      $this->gitLabProjectId,
      [$mocked_project],
    );

    $variables = $this->getMockGitLabVariables();
    $variables[] = [
      'environment_scope' => '*',
      'key' => 'PHP_VERSION',
      'masked' => FALSE,
      'protected' => FALSE,
      'value' => '8.1',
      'variable_type' => 'env_var',
    ];
    $projects->variables($this->gitLabProjectId)->willReturn($variables);
    $projects->updateVariable($this->gitLabProjectId, Argument::type('string'), Argument::type('string'))
      ->willThrow(RuntimeException::class);

    $gitlab_client->projects()->willReturn($projects);
    $this->command->setGitLabClient($gitlab_client->reveal());
    $this->executeCommand([
      '--gitlab-host-name' => $this->gitLabHost,
      '--gitlab-token' => $this->gitLabToken,
      'applicationUuid' => self::$application_uuid,
      'php-version' => '8.1',
    ]);

    $output = $this->getDisplay();
    $this->assertStringContainsString('Unable to update the PHP version to 8.1', $output);
  }

  /**
   * Test for successful PHP version update.
   */
  public function testPhpVersionUpdate(): void {
    $this->mockApplicationRequest();
    $gitlab_client = $this->prophet->prophesize(Client::class);
    $this->mockGitLabUsersMe($gitlab_client);
    $mocked_project = $this->getMockedGitLabProject($this->gitLabProjectId);
    $mocked_project['jobs_enabled'] = TRUE;
    $projects = $this->mockGetGitLabProjects(
      self::$application_uuid,
      $this->gitLabProjectId,
      [$mocked_project],
    );

    $variables = $this->getMockGitLabVariables();
    $variables[] = [
      'environment_scope' => '*',
      'key' => 'PHP_VERSION',
      'masked' => FALSE,
      'protected' => FALSE,
      'value' => '8.1',
      'variable_type' => 'env_var',
    ];
    $projects->variables($this->gitLabProjectId)->willReturn($variables);
    $projects->updateVariable($this->gitLabProjectId, Argument::type('string'), Argument::type('string'));

    $gitlab_client->projects()->willReturn($projects);

    $this->command->setGitLabClient($gitlab_client->reveal());
    $this->executeCommand([
      '--gitlab-host-name' => $this->gitLabHost,
      '--gitlab-token' => $this->gitLabToken,
      'applicationUuid' => self::$application_uuid,
      'php-version' => '8.1',
    ]);

    $output = $this->getDisplay();
    $this->assertStringContainsString('PHP version is updated to 8.1 successfully!', $output);
  }

}
