<?php

namespace Acquia\Ads\Tests\Api;

use Acquia\Ads\Command\Api\ApiListCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class ApiListCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command
    {
        return new ApiListCommand();
    }

    /**
     * Tests the 'api:list' command.
     */
    public function testApiListCommand(): void
    {
    }
}
