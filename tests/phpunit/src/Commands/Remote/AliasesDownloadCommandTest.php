<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\AliasesDownloadCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class AliasesDownloadCommandTest.
 *
 * @property AliasesDownloadCommand $command
 * @package Acquia\Cli\Tests\Remote
 */
class AliasesDownloadCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new AliasesDownloadCommand();
  }

  /**
   * Tests the 'remote:aliases:download' commands.
   */
  public function testRemoteAliasesDownloadCommand(): void {
  }

}
