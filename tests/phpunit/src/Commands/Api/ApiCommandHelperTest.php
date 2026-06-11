<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionMethod;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

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
     * The parsed spec must be served from the fallback cache when the warmed
     * PHP cache file is absent, instead of being re-parsed on every invocation.
     */
    public function testGetCloudApiSpecUsesFallbackCacheWhenWarmedCacheFileIsMissing(): void
    {
        $specFilePath = tempnam(sys_get_temp_dir(), 'acli_spec_test_');
        file_put_contents($specFilePath, json_encode([
            'components' => [],
            'paths' => [],
        ], JSON_THROW_ON_ERROR));
        $warmedCacheFilePath = dirname(__DIR__, 5) . '/var/cache/' . basename($specFilePath) . '.cache';
        putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');

        try {
            $output = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG);
            $helper = new ApiCommandHelper(new ConsoleLogger($output));
            $method = new ReflectionMethod(ApiCommandHelper::class, 'getCloudApiSpec');

            // First call parses the spec file and warms the caches.
            $firstSpec = $method->invoke($helper, $specFilePath);
            $this->assertStringContainsString('Rebuilding caches', $output->fetch());

            // Even with the warmed cache file gone, the fallback cache must
            // serve the parsed spec rather than re-parsing the spec file.
            unlink($warmedCacheFilePath);
            $this->resetPhpArrayAdapterStaticCache();
            $secondSpec = $method->invoke($helper, $specFilePath);
            $this->assertSame($firstSpec, $secondSpec);
            $this->assertStringNotContainsString('Rebuilding caches', $output->fetch());
        } finally {
            putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE');
            if (file_exists($specFilePath)) {
                unlink($specFilePath);
            }
            if (file_exists($warmedCacheFilePath)) {
                unlink($warmedCacheFilePath);
            }
        }
    }

    /**
     * Forget warmed cache files in PhpArrayAdapter's static in-process cache.
     *
     * Simulates a fresh CLI invocation, in which a deleted warmed cache file
     * would no longer be readable.
     */
    private function resetPhpArrayAdapterStaticCache(): void
    {
        $property = new \ReflectionProperty(PhpArrayAdapter::class, 'valuesCache');
        $property->setValue(null, []);
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
