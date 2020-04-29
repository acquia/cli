<?php

namespace Acquia\Ads\Tests;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand;

class ListCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command
    {
        return new ListCommand();
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testApiCommandsHidden(): void
    {
        $this->command = new ListCommand();
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringNotContainsString('api:', $output);
        $this->assertStringContainsString('api', $output);
    }
}
