<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\Command\Acsf\AcsfListCommand;
use Acquia\Cli\Command\Acsf\AcsfListCommandBase;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Self\ListCommand;

/**
 * @property AcsfListCommandBase $command
 */
class AcsfListCommandTest extends AcsfCommandTestBase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->application->addCommands($this->getApiCommands());
    }

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(AcsfListCommand::class);
    }

    /**
     * @throws \Exception
     */
    public function testAcsfListCommand(): void
    {
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('acsf:api', $output);
        $this->assertStringContainsString('acsf:api:ping', $output);
        $this->assertStringContainsString('acsf:info:audit-events-find', $output);
    }

    /**
     * @throws \Exception
     */
    public function testApiNamespaceListCommand(): void
    {
        $this->command = $this->injectCommand(AcsfListCommandBase::class);
        $name = 'acsf:api';
        $this->command->setName($name);
        $this->command->setNamespace($name);
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('acsf:api:ping', $output);
    }

    /**
     * @throws \Exception
     */
    public function testListCommand(): void
    {
        $this->command = $this->injectCommand(ListCommand::class);
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('acsf:api', $output);
        $this->assertStringNotContainsString('acsf:api:ping', $output);
    }
}
