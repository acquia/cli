<?php

namespace Acquia\Ads\Tests\Remote;

use Acquia\Ads\Command\Remote\DrushCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class DrushCommandTest extends CommandTestBase
{

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
