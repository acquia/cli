<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\Pull\PullCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestBase;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class PullCommandTest.
 *
 * @property \Acquia\Cli\Command\Pull\PullCommand $command
 * @package Acquia\Cli\Tests\Commands\Pull
 */
class PullCommandTest extends PullCommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(PullCommand::class);
  }

  public function testMissingLocalRepo(): void {
    // Unset repo root. Mimics failing to find local git repo. Command must be re-created
    // to re-inject the parameter into the command.
    $this->acliRepoRoot = '';
    $this->command = $this->createCommand();
    try {
      $inputs = [
        // Would you like to clone a project into the current directory?
        'n',
      ];
      $this->executeCommand([], $inputs);
    }
    catch (AcquiaCliException $e) {
      $this->assertEquals('Please execute this command from within a Drupal project directory or an empty directory', $e->getMessage());
    }
  }

}
