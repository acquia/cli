<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionMethod;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use ReflectionProperty;
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
     * A fully hidden namespace that sorts before visible namespaces must be
     * skipped with `continue`, not `break`, so the later visible namespaces are
     * still listed. Also asserts that every visible namespace is returned, not
     * just the first.
     */
    public function testHiddenNamespaceBeforeVisibleNamespacesListsAllVisible(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:hidden:only', true),
            $this->createMockApiCommand('api:alpha:create', false),
            $this->createMockApiCommand('api:beta:create', false),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $this->assertArrayNotHasKey('api:hidden', $listCommands);
        // `break` instead of `continue` would stop at the hidden namespace and
        // never reach alpha/beta.
        $this->assertArrayHasKey('api:alpha', $listCommands);
        // Returning only the first element would drop beta.
        $this->assertArrayHasKey('api:beta', $listCommands);
        $this->assertCount(2, $listCommands);
    }

    /**
     * The generated list command must have its name, namespace, (empty) aliases,
     * and description set.
     */
    public function testGeneratedListCommandHasExpectedProperties(): void
    {
        $apiCommands = [
            $this->createMockApiCommand('api:widgets:create', false),
        ];
        $listCommands = $this->generateApiListCommands($apiCommands);
        $command = $listCommands['api:widgets'];
        $this->assertSame('api:widgets', $command->getName());
        $this->assertSame('List all API commands for the widgets resource', $command->getDescription());
        // createListCommand() returns an ApiListCommand whose AsCommand attribute
        // declares aliases ['api']; the helper must clear them.
        $this->assertSame([], $command->getAliases());
        $namespaceProperty = (new ReflectionMethod($command, 'setNamespace'))
            ->getDeclaringClass()
            ->getProperty('namespace');
        $this->assertSame('api:widgets', $namespaceProperty->getValue($command));
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
            // serve the parsed spec rather than re-parsing the spec file. Use a
            // fresh helper so the per-process memoization does not short-circuit
            // the fallback-cache lookup under test.
            unlink($warmedCacheFilePath);
            $this->resetPhpArrayAdapterStaticCache();
            $freshHelper = new ApiCommandHelper(new ConsoleLogger($output));
            $secondSpec = $method->invoke($freshHelper, $specFilePath);
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
     * The lazy factory map must register exactly the same set of commands as the
     * eager getApiCommands(), so switching to lazy registration changes nothing
     * a user can observe.
     */
    public function testGetApiCommandFactoriesMatchEagerCommands(): void
    {
        // Build the registration manifest from source rather than the cache, so
        // the manifest-building logic is actually exercised under test.
        putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=0');
        try {
            $helper = new ApiCommandHelper($this->logger);
            // getApiCommands() returns a flat list that may repeat a name;
            // Symfony dedupes on registration, as does the keyed factory map, so
            // compare the unique name sets.
            $eagerNames = array_unique(array_map(static fn ($c) => $c->getName(), $helper->getApiCommands(self::$apiSpecFixtureFilePath, 'api', $this->getCommandFactory())));
            sort($eagerNames);

            $factories = $helper->getApiCommandFactories(self::$apiSpecFixtureFilePath, 'api', $this->getCommandFactory());
            $lazyNames = array_keys($factories);
            sort($lazyNames);

            $this->assertSame(array_values($eagerNames), $lazyNames);
            // Every entry must be a closure that is only invoked on demand.
            foreach ($factories as $factory) {
                $this->assertInstanceOf(\Closure::class, $factory);
            }
            // Skipped commands are never registered; namespace list commands are.
            $this->assertArrayNotHasKey('api:ssh-key:list', $factories);
            $this->assertArrayHasKey('api:accounts', $factories);
        } finally {
            putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE');
        }
    }

    /**
     * Invoking a factory must lazily build a command identical to the one the
     * eager path produces (name, description, and input definition).
     */
    public function testGetApiCommandFactoriesBuildConfiguredCommandOnDemand(): void
    {
        putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=0');
        try {
            $helper = new ApiCommandHelper($this->logger);
            $name = 'api:accounts:find';
            $eager = $helper->getApiCommands(self::$apiSpecFixtureFilePath, 'api', $this->getCommandFactory());
            $eagerCommand = null;
            foreach ($eager as $candidate) {
                if ($candidate->getName() === $name) {
                    $eagerCommand = $candidate;
                    break;
                }
            }
            $this->assertNotNull($eagerCommand);

            $factories = $helper->getApiCommandFactories(self::$apiSpecFixtureFilePath, 'api', $this->getCommandFactory());
            $this->assertArrayHasKey($name, $factories);
            $lazyCommand = $factories[$name]();

            $this->assertSame($eagerCommand->getName(), $lazyCommand->getName());
            $this->assertSame($eagerCommand->getDescription(), $lazyCommand->getDescription());
            $this->assertNotEmpty($lazyCommand->getDescription());
            $this->assertSame(
                array_keys($eagerCommand->getDefinition()->getOptions()),
                array_keys($lazyCommand->getDefinition()->getOptions())
            );
            $this->assertSame(
                array_keys($eagerCommand->getDefinition()->getArguments()),
                array_keys($lazyCommand->getDefinition()->getArguments())
            );
        } finally {
            putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE');
        }
    }

    /**
     * A built command must carry its responses, servers, and the right help
     * text for normal, pre-release, and deprecated endpoints.
     */
    public function testBuildApiCommandConfiguresResponsesServersAndHelp(): void
    {
        $normal = $this->getApiCommandByName('api:accounts:find');
        $this->assertNotNull($normal);
        $this->assertNotEmpty($this->readProtected($normal, 'responses'));
        $this->assertNotEmpty($this->readProtected($normal, 'servers'));
        $this->assertStringContainsString('For more help', $normal->getHelp());
        $this->assertStringNotContainsString('pre-release', $normal->getHelp());
        $this->assertStringNotContainsString('deprecated and may be removed', $normal->getHelp());

        $preRelease = $this->getApiCommandByName('api:codebases:applications:list');
        $this->assertNotNull($preRelease);
        // The notice must be appended to (not replace) the base help text.
        $this->assertStringContainsString('For more help', $preRelease->getHelp());
        $this->assertStringContainsString('pre-release', $preRelease->getHelp());

        $deprecated = $this->getApiCommandByName('api:applications:hosting-settings-list');
        $this->assertNotNull($deprecated);
        $this->assertStringContainsString('For more help', $deprecated->getHelp());
        $this->assertStringContainsString('deprecated and may be removed', $deprecated->getHelp());
    }

    private function readProtected(object $object, string $property): mixed
    {
        return (new \ReflectionProperty($object, $property))->getValue($object);
    }

    /**
     * The manifest builder must skip (continue past), not break out of, both
     * methods that lack an x-cli-name and methods whose command is skipped, so
     * that a later valid method on the same path is still registered.
     */
    public function testBuildApiSpecManifestContinuesPastIgnoredMethods(): void
    {
        $spec = [
            'paths' => [
                // 'get' has no x-cli-name and must be skipped without dropping 'post'.
                '/a' => [
                    'get' => ['responses' => []],
                    'post' => ['x-cli-name' => 'a:create', 'summary' => 's', 'responses' => []],
                ],
                // 'get' is an explicitly skipped command and must not drop 'post'.
                '/b' => [
                    'get' => ['x-cli-name' => 'ide:create', 'summary' => 's', 'responses' => []],
                    'post' => ['x-cli-name' => 'b:create', 'summary' => 's', 'responses' => []],
                ],
            ],
        ];
        $manifest = $this->invokeApiCommandHelperMethod('buildApiSpecManifest', [$spec]);
        $this->assertArrayHasKey('a:create', $manifest);
        $this->assertArrayHasKey('b:create', $manifest);
        $this->assertArrayNotHasKey('ide:create', $manifest);
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
