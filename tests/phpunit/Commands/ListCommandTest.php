<?php

namespace Acquia\Ads\Tests\Api;

use Acquia\Ads\Command\Api\ApiListCommand;
use Acquia\Ads\Tests\CommandTestBase;
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
     * Tests the 'list' command.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testListCommand(): void
    {
        $this->executeCommand();
        $output = $this->getDisplay();
        //$this->assertStringContainsString('api', $output);
        $this->assertStringNotContainsString('api:', $output);
    }

    // @todo Add a test that invokes ads via bash from a Process rather than via
    // the command tester class. This will test the bash bootstrap process which
    // is bypassed otherwise in phpunit testing.
}
