<?php

namespace Acquia\Ads\Tests\Commands\Api;

use Acquia\Ads\Command\Api\ApiListCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class ApiListCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command {
        return new ApiListCommand();
    }

    /**
     * Tests the 'api:list' command.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testApiListCommand(): void {
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString(' api:accounts:ssh-keys-list', $output);
    }

}
