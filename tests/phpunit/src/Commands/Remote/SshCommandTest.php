<?php

namespace Acquia\Ads\Tests\Commands\Remote;

use Acquia\Ads\Command\Remote\SshCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class SshCommandTest
 * @property SshCommand $command
 * @package Acquia\Ads\Tests\Remote
 */
class SshCommandTest extends CommandTestBase
{

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
