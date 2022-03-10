<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\MakeDocsCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class MakeDocsCommandTest.
 *
 * @property \Acquia\Cli\Command\MakeDocsCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class MakeDocsCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(MakeDocsCommand::class);
  }

  /**
   * Tests the 'make:docs' command.
   *
   * @throws \Exception
   */
  public function testMakeDocsCommand(): void {
    $this->executeCommand([], []);
    $output = $this->getDisplay();
    $this->assertStringContainsString('Acquia CLI @package_version@', $output);
  }

}
