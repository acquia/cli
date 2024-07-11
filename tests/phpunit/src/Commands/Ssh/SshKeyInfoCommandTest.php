<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyInfoCommand;
use Acquia\Cli\Tests\CommandTestBase;

class SshKeyInfoCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(SshKeyInfoCommand::class);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->setupFsFixture();
        $this->command = $this->createCommand();
    }

    public function testInfo(): void
    {
        $this->mockListSshKeysRequest();

        $inputs = [
        // Choose key.
            '0',
        ];
        $this->executeCommand([], $inputs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Choose an SSH key to view', $output);
        $this->assertStringContainsString('SSH key property       SSH key value', $output);
        $this->assertStringContainsString('UUID                   02905393-65d7-4bef-873b-24593f73d273', $output);
        $this->assertStringContainsString('Label                  PC Home', $output);
        $this->assertStringContainsString('Fingerprint (md5)      5d:23:fb:45:70:df:ef:ad:ca:bf:81:93:cd:50:26:28', $output);
        $this->assertStringContainsString('Created at             2017-05-09T20:30:35.000Z', $output);
    }
}
