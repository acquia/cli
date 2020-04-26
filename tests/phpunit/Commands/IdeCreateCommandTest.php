<?php

namespace Acquia\Ads\Tests;

use Acquia\Ads\Command\Ide\IdeCreateCommand;
use Symfony\Component\Console\Command\Command;

class IdeCreateCommandTest extends CommandTestBase
{

    /**
     * Tests the 'ide:create' command.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testCreate(): void
    {
        $this->getCommandTester()->setInputs([
            // Would you like ADS to search for a Cloud application that matches your local git config?
            'No',
            // Please select the application for which you'd like to create a new IDE
            0,
            // Please enter a label for your Remote IDE:
            'Test IDE',
        ]);

        // @todo Create project fixture with .git and docroot dir.
        // @todo Set current working directory.
        // @todo Print output even when failure.
        $this->executeCommand();

        // Expected output:
        // Creating your Remote IDE
        // Getting IDE information
        // Waiting for DNS to propagate
        // Your IDE is ready!
        // Your IDE URL: https://5f5832bd-e335-438f-9b8a-823e0c2a1179.ide.ahdev.cloud
        // Your Drupal Site URL: https://5f5832bd-e335-438f-9b8a-823e0c2a1179.web.ahdev.cloud

        print $this->getDisplay();

        $output = $this->getDisplay();
    }

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command {
        return new IdeCreateCommand();
    }
}
