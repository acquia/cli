<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Command\Api\ApiListCommandBase;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Self\ListCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property ApiListCommandBase $command
 */
class ApiListCommandTest extends CommandTestBase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->application->addCommands($this->getApiCommands());
    }

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(ApiListCommand::class);
    }

    public function testApiListCommand(): void
    {
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString(' api:accounts:ssh-keys-list', $output);
    }

    public function testApiNamespaceListCommand(): void
    {
        $this->command = $this->injectCommand(ApiListCommandBase::class);
        $name = 'api:accounts';
        $this->command->setName($name);
        $this->command->setNamespace($name);
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('api:accounts:', $output);
        $this->assertStringContainsString('api:accounts:ssh-keys-list', $output);
        // Without the namespace argument the list would show every namespace; this
        // assertion proves the namespace key is actually passed to the list command.
        $this->assertStringNotContainsString('api:applications:', $output);
    }

    public function testListCommand(): void
    {
        $this->command = $this->injectCommand(ListCommand::class);
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString(' api:accounts', $output);
        $this->assertStringNotContainsString(' api:accounts:ssh-keys-list', $output);
    }

    public function testListCommandSubNamespace(): void
    {
        $this->command = $this->injectCommand(ListCommand::class);
        $this->executeCommand(['namespace' => 'api:accounts']);
        $output = $this->getDisplay();
        $this->assertStringContainsString('api:accounts:ssh-keys-list', $output);
    }

    /**
     * Kills the ConcatOperandRemoval mutation on ListCommand:57.
     *
     * The condition uses `$prefix . ':'` to distinguish 'api' from 'apifoo'.
     * Output-based assertions can't catch this (the namespace filter removes
     * non-matching commands either way), so we inspect hidden state directly.
     * With the mutation str_starts_with($requestedNs, 'api') is true for 'apifoo',
     * so the hide-loop is skipped and api:* sub-commands remain visible.
     */
    public function testListCommandHidesApiSubCommandsForUnrelatedNamespace(): void
    {
        $this->command = $this->injectCommand(ListCommand::class);
        // 'apifoo' starts with 'api' but not 'api:' — the edge case the ':' suffix guards.
        try {
            $this->executeCommand(['namespace' => 'apifoo']);
        } catch (\Symfony\Component\Console\Exception\NamespaceNotFoundException) {
            // Expected — 'apifoo' is not a registered namespace.
            // The hiding logic (ListCommand:55-69) runs before the describe call throws,
            // so we still check the hidden state below.
        }
        $apiSubCommand = $this->application->find('api:accounts:ssh-keys-list');
        $this->assertTrue($apiSubCommand->isHidden(), 'api:* sub-commands must be hidden when the requested namespace is unrelated to api');
    }
}
