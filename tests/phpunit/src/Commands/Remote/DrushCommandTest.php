<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\DrushCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class DrushCommandTest.
 *
 * @property DrushCommand $command
 * @package Acquia\Cli\Tests\Remote
 */
class DrushCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new DrushCommand();
  }

  /**
   * Tests the 'remote:drush' commands.
   */
  public function testRemoteDrushCommand(): void {
  }

}
