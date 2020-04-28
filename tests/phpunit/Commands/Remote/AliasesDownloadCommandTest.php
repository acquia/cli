<?php

namespace Acquia\Ads\Tests\Remote;

use Acquia\Ads\Command\Remote\AliasesDownloadCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class AliasesDownloadCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command
    {
        return new AliasesDownloadCommand();
    }

    /**
     * Tests the 'remote:aliases:download' commands.
     */
    public function testRemoteAliasesDownloadCommand(): void
    {
    }
}
