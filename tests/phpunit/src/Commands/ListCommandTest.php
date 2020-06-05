<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand;

/**
 * Class ListCommandTest.
 *
 * @property ListCommand $command
 */
class ListCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ListCommand::class);
  }

  /**
   * Tests the 'list' command.
   *
   * @throws \Exception
   */
  public function testListCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringNotContainsString('api:', $output);
  }

}
