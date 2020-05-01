<?php

namespace Acquia\Ads\Tests\Remote;

use Acquia\Ads\Command\Remote\DrushCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class DrushCommandTest
 * @property DrushCommand $command
 * @package Acquia\Ads\Tests\Remote
 */
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
