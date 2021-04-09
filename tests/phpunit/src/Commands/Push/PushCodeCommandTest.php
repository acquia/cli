<?php

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\Pull\PullCodeCommand;
use Acquia\Cli\Command\Pull\PullCommand;
use Acquia\Cli\Command\Pull\PullDatabaseCommand;
use Acquia\Cli\Command\Pull\PullFilesCommand;
use Acquia\Cli\Command\Push\PushArtifactCommand;
use Acquia\Cli\Command\Push\PushFilesCommand;
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
 * Class PushCodeCommandTest.
 *
 * @property \Acquia\Cli\Command\Push\PushArtifactCommand $command
 * @package Acquia\Cli\Tests\Commands\Push
 */
class PushCodeCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(PushArtifactCommand::class);
  }

  public function testPushCode(): void {
    $this->executeCommand([], []);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please use git to push code changes upstream.', $output);
  }

}
