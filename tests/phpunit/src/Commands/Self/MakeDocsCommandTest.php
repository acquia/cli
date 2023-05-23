<?php

namespace Acquia\Cli\Tests\Commands\Self;

use Acquia\Cli\Command\Self\MakeDocsCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Self\MakeDocsCommand $command
 */
class MakeDocsCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(MakeDocsCommand::class);
  }

  public function testMakeDocsCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Console Tool', $output);
    $this->assertStringContainsString('============', $output);
    $this->assertStringContainsString('- `help`_', $output);
  }

}
