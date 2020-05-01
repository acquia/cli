<?php

namespace Acquia\Ads\Tests\Ide;

use Acquia\Ads\Command\Ide\IdeOpenCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class IdeListCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command {
        return new IdeOpenCommand();
    }

    /**
     * Tests the 'ide:list' commands.
     */
    public function testIdeListCommand(): void {
    }

}
