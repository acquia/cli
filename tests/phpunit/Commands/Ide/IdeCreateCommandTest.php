<?php

namespace Acquia\Ads\Tests\Ide;

use Acquia\Ads\Command\Ide\IdeCreateCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class IdeCreateCommandTest extends CommandTestBase
{

    /**
     * Tests the 'ide:create' command.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testCreate(): void
    {
        $inputs = [
          // @todo Don't assume we're authenticated!
            // Please select the application for which you'd like to create a new IDE
            '0',
            // Please enter a label for your Remote IDE:
            'Test IDE',
        ];

        $this->executeCommand([], $inputs);

        // Expected output:
        // Creating your Remote IDE
        // Getting IDE information
        // Waiting for DNS to propagate
        // Your IDE is ready!
        // Your IDE URL: https://5f5832bd-e335-438f-9b8a-823e0c2a1179.ide.ahdev.cloud
        // Your Drupal Site URL: https://5f5832bd-e335-438f-9b8a-823e0c2a1179.web.ahdev.cloud

        $output = $this->getDisplay();
    }

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command
    {
        return new IdeCreateCommand();
    }
}
