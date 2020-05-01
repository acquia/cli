<?php

namespace Acquia\Ads\Tests\Commands;

use Acquia\Ads\Command\Api\ApiListCommand;
use Acquia\Ads\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Process\Process;

/**
 * Class ListCommandTest
 * @property ListCommand $command
 * @package Acquia\Ads\Tests\Api
 */
class ListCommandTest extends CommandTestBase
{

    /**
     * {@inheritdoc}
     */
    protected function createCommand(): Command {
        return new ListCommand();
    }

    /**
     * Tests the 'list' command.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testListCommand(): void {
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringNotContainsString('api:', $output);
    }

    /**
     * Tests the execution of bin/ads via bash.
     */
    public function testBinExec() {
        $process = new Process(['./ads', 'list'], __DIR__ . '/../../../../bin');
        $process->mustRun();
        $this->assertStringContainsString('api', $process->getOutput());
        $this->assertStringNotContainsString('api:ssh-key:create', $process->getOutput());
    }

}
