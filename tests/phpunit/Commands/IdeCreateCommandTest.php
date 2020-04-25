<?php

namespace Acquia\Ads\Tests;

use PHPUnit\Framework\TestCase;
use Acquia\Ads\Command\Ide\IdeCreateCommand;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Tester\CommandTester;

class IdeCreateCommandTest extends TestCase
{

    /**
     * Tests the 'ide:create' command.
     */
    public function testCreate(): void
    {
        $this->application->add(new IdeCreateCommand());

        $command = $this->application->find('ide:create');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
          'command' => $command->getName(),
        ), ['verbosity' => Output::VERBOSITY_VERBOSE]);

        $output = $commandTester->getDisplay();
    }

}
