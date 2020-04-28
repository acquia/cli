<?php

namespace Acquia\Ads\Tests\Ide;

use Acquia\Ads\Command\Ide\IdeDeleteCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class IdeDeleteCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command
    {
        return new IdeDeleteCommand();
    }

    /**
     * Tests the 'ide:delete' command.
     */
    public function testIdeDeleteCommand(): void
    {
    }
}
