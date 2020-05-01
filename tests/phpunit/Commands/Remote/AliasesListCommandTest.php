<?php

namespace Acquia\Ads\Tests\Remote;

use Acquia\Ads\Command\Remote\AliasListCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class AliasesListCommandTest extends CommandTestBase
{

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
