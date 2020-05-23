<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class ListCommandTest.
 *
 * @property ListCommand $command
 * @package Acquia\Cli\Tests\Api
 */
class ListCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new ListCommand();
  }

  /**
   * Tests the 'list' command.
   *
   */
  public function testListCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringNotContainsString('api:', $output);
  }

}
