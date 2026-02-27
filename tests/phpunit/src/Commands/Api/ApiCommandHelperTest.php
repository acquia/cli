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

    /**
     * Kill LogicalAnd (&& -> ||): visible requires BOTH "in namespace" AND "not hidden".
     * When a namespace has only hidden commands, no list command must be created.
     */
    public function testNamespaceWithOnlyHiddenCommandsDoesNotGetListCommand(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:foo:list', true),
            $this->createMockApiCommand('api:foo:create', true),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $this->assertArrayNotHasKey('api:foo', $listCommands);
    }

    /**
     * Kill LogicalAnd: when a namespace has at least one non-hidden command, list command is created.
     */
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

    /**
     * Kill ConcatOperandRemoval (namespace . ':' -> ':'): match must be exact namespace prefix.
     * When namespace "empty" has only hidden commands, we must NOT add api:empty even if another
     * visible command exists (mutation would match any ':' + visible and wrongly add api:empty).
     */
    public function testNamespaceMatchRequiresNamespaceColonPrefix(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:empty:list', true),
            $this->createMockApiCommand('api:other:create', false),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $this->assertArrayNotHasKey('api:empty', $listCommands);
        $this->assertArrayHasKey('api:other', $listCommands);
    }

    /**
     * Kill ConcatOperandRemoval (namespace . ':' -> namespace): substring must not match.
     * Namespace "a" has only hidden command; "accounts" has visible. We must NOT add api:a
     * (mutation would match str_contains("api:accounts:list", "a") and wrongly add api:a).
     */
    public function testNamespaceMatchDoesNotUseBareSubstring(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:a:list', true),
            $this->createMockApiCommand('api:accounts:list', false),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $this->assertArrayNotHasKey('api:a', $listCommands);
        $this->assertArrayHasKey('api:accounts', $listCommands);
        $this->assertCount(1, $listCommands);
    }

    /**
     * Kill LogicalAnd on line 543: we must only add when BOTH "namespace not yet in list" AND "hasVisibleCommand".
     * So when hasVisibleCommand is false we must not add (already covered above).
     * When we would duplicate the same namespace we must not add a second list command (key is $name so we overwrite; the check uses $namespace which is a bug, but we still get one list per namespace). Assert exactly one list per visible namespace.
     */
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
}
