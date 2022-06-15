<?php

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\AppOpenCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class TaskWaitCommand.
 *
 * @property \Acquia\Cli\Command\App\TaskWaitCommand $command
 */
class TaskWaitCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(AppOpenCommand::class);
  }

  /**
   *
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Exception
   */
  public function testTaskWaitCommand(): void {
    $application_uuid = '94835c3e-b112-4660-a14d-d541906c205b';

    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->executeCommand([], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
  }

}
