<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\AliasListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class AliasesListCommandTest.
 *
 * @property AliasListCommand $command
 * @package Acquia\Cli\Tests\Remote
 */
class AliasesListCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new AliasListCommand();
  }

  /**
   * Tests the 'remote:aliases:list' commands.
   */
  public function testRemoteAliasesListCommand(): void {
  }

}
