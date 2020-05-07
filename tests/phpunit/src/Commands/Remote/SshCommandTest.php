<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\SshCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class SshCommandTest.
 *
 * @property SshCommand $command
 * @package Acquia\Cli\Tests\Remote
 */
class SshCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new SshCommand();
  }

  /**
   * Tests the 'remote:ssh' commands.
   */
  public function testRemoteAliasesDownloadCommand(): void {
  }

}
