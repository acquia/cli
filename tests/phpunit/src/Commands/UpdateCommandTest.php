<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\UnlinkCommand;
use Acquia\Cli\Command\UpdateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class UpdateCommandTest.
 *
 * @property \Acquia\Cli\Command\UpdateCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class UpdateCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new UpdateCommand();
  }

  public function testNonPharException() {
    try {
      $this->executeCommand([], []);
    }
    catch (\Exception $e) {
      $this->assertStringContainsString('update only works when running the phar version of ', $e->getMessage());
    }
  }

}
