<?php

namespace Acquia\Ads\Tests\Api;

use Acquia\Ads\Command\Api\ApiCommandBase;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class ApiCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command
    {
        return new ApiCommandBase();
    }

    /**
     * Tests the 'api:*' commands.
     */
    public function testApiCommand(): void
    {
    }
}
