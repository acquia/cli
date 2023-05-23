<?php

namespace Acquia\Cli\Tests\Commands\Push;

use Acquia\Cli\Command\Push\PushCodeCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Push\PushCodeCommand $command
 */
class PushCodeCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(PushCodeCommand::class);
  }

  public function testPushCode(): void {
    $this->executeCommand();
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Use git to push code changes upstream.', $output);
  }

}
