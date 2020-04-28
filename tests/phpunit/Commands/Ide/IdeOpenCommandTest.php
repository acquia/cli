<?php

namespace Acquia\Ads\Tests\Ide;

use Acquia\Ads\Command\Ide\IdeOpenCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class IdeOpenCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command
    {
        return new IdeOpenCommand();
    }

    /**
     * Tests the 'ide:open' command.
     */
    public function testIdeOpenCommand(): void
    {
    }
}
