<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;

/**
 * Tests for ApiCommandHelper::generateApiListCommands (via reflection).
 * Kills mutations in the namespace visibility and list-command creation logic.
 */
class ApiCommandHelperTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(ApiListCommand::class);
    }

    /**
     * Create a mock command with the given name and hidden state.
     */
    private function createMockApiCommand(string $name, bool $hidden): Command
    {
        $cmd = new Command($name);
        $cmd->setName($name);
        $cmd->setHidden($hidden);
        return $cmd;
    }

    /**
     * Call private generateApiListCommands via reflection.
     *
     * @param Command[] $apiCommands
     * @return \Acquia\Cli\Command\Api\ApiListCommandBase[]
     */
    private function generateApiListCommands(array $apiCommands, string $commandPrefix = 'api'): array
    {
        $helper = new ApiCommandHelper($this->logger);
        $ref = new ReflectionMethod(ApiCommandHelper::class, 'generateApiListCommands');
        return $ref->invoke($helper, $apiCommands, $commandPrefix, $this->getCommandFactory());
    }

    public function testNamespaceWithVisibleCommandGetsListCommand(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:foo:list', true),
            $this->createMockApiCommand('api:foo:create', false),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $this->assertArrayHasKey('api:foo', $listCommands);
        $this->assertSame('api:foo', $listCommands['api:foo']->getName());
    }

    /**
     * Kill LogicalAndNegation: condition must be (in namespace AND not hidden), not its negation.
     * Ensures we only set hasVisibleCommand when we find a command that matches both.
     */
    public function testNamespaceWithOneVisibleAndOneHiddenGetsListCommand(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:bar:list', false),
            $this->createMockApiCommand('api:bar:create', true),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $this->assertArrayHasKey('api:bar', $listCommands);
    }

    public function testOnlyOneListCommandPerVisibleNamespace(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:accounts:list', false),
            $this->createMockApiCommand('api:accounts:create', false),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $this->assertCount(1, $listCommands);
        $this->assertArrayHasKey('api:accounts', $listCommands);
    }

    /**
     * namespaceHasVisibleCommand must scan the full command list (continue on mismatch).
     * If continue were replaced with break, a visible command after another namespace would be missed.
     */
    public function testNamespaceVisibleCommandAfterOtherNamespaceStillGetsListCommand(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:mix:first', true),
            $this->createMockApiCommand('api:other:list', false),
            $this->createMockApiCommand('api:mix:second', false),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $this->assertArrayHasKey('api:mix', $listCommands, 'mix has a visible command after other namespace; break would skip it.');
    }

    /**
     * When every sub-command under a namespace is hidden, omit the namespace list command.
     */
    public function testNamespaceWithAllHiddenCommandsDoesNotGetListCommand(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:baz:list', true),
            $this->createMockApiCommand('api:baz:create', true),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $this->assertArrayNotHasKey('api:baz', $listCommands);
    }

    /**
     * Calls private or protected method of ApiCommandHelper class via reflection.
     *
     * @throws \ReflectionException
     */
    private function invokeApiCommandHelperMethod(string $methodName, array $args = []): mixed
    {
        $commandHelper = new ApiCommandHelper($this->logger);
        $refClass = new ReflectionMethod($commandHelper::class, $methodName);
        return $refClass->invokeArgs($commandHelper, $args);
    }

    /**
     * Test that addPostArgumentUsageToExample correctly formats a flat array with a single item.
     */
    public function testAddPostArgumentUsageToExampleFlatArraySingleItem(): void
    {
        $result = $this->invokeApiCommandHelperMethod(
            'addPostArgumentUsageToExample',
            [
                [
                    'content' => [
                        'application/json' => [
                            'example' => ['tags' => ['drupal']],
                        ],
                    ],
                ],
                'tags',
                ['type' => 'array'],
                'option',
                '',
                [],
            ]
        );
        $this->assertSame("--tags='drupal'", $result);
    }
}
