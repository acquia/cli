<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Pull\PullCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Pull\PullCommand $command
 */
class PullCommandTest extends PullCommandTestBase {

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->setupFsFixture();
  }

  protected function createCommand(): Command {
    return $this->injectCommand(PullCommand::class);
  }

  public function testMissingLocalRepo(): void {
    // Unset repo root. Mimics failing to find local git repo. Command must be re-created
    // to re-inject the parameter into the command.
    $this->acliRepoRoot = '';
    $this->removeMockGitConfig();
    $this->command = $this->createCommand();
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Execute this command from within a Drupal project directory or an empty directory');
    $inputs = [
      // Would you like to clone a project into the current directory?
      'n',
    ];
    $this->executeCommand([], $inputs);
  }

}
