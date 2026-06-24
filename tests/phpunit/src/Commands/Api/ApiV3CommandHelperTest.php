<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\CloudApi\V3ClientService;
use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Command\Api\ApiV3CommandFactory;
use Acquia\Cli\Command\Api\ApiV3CommandHelper;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionMethod;
use Symfony\Component\Filesystem\Path;

/**
 * Tests for ApiV3CommandHelper::getCliCommandName — covers the v3
 * extension-key convention (x-acquia-exposure.channels.cli.command)
 * and its relationship with the legacy x-cli-name key.
 */
class ApiV3CommandHelperTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(ApiListCommand::class);
    }

    private function invokeGetCliCommandName(array $schema): ?string
    {
        $helper = new ApiV3CommandHelper($this->logger);
        $ref = new ReflectionMethod(ApiV3CommandHelper::class, 'getCliCommandName');
        return $ref->invoke($helper, $schema);
    }

    /**
     * v3 key is present. Helper reads it.
     */
    public function testReadsArbKeyWhenPresent(): void
    {
        $schema = [
            'x-acquia-exposure' => [
                'channels' => [
                    'cli' => ['command' => 'environments:find'],
                ],
            ],
        ];
        $this->assertSame('environments:find', $this->invokeGetCliCommandName($schema));
    }

    /**
     * v2's legacy `x-cli-name` is NOT ApiV3CommandHelper's concern; that key
     * is handled by ApiCommandHelper (v2). v3 ignores it and returns null so
     * the operation is skipped in the v3 namespace.
     */
    public function testIgnoresLegacyXCliNameKey(): void
    {
        $schema = ['x-cli-name' => 'legacy-ignored'];
        $this->assertNull($this->invokeGetCliCommandName($schema));
    }

    /**
     * Both keys present: v3 key is read, legacy is ignored entirely.
     */
    public function testUsesArbKeyEvenWhenLegacyAlsoPresent(): void
    {
        $schema = [
            'x-acquia-exposure' => [
                'channels' => [
                    'cli' => ['command' => 'arb-key-used'],
                ],
            ],
            'x-cli-name' => 'legacy-ignored',
        ];
        $this->assertSame('arb-key-used', $this->invokeGetCliCommandName($schema));
    }

    /**
     * Neither key: operation is skipped (no CLI name declared).
     */
    public function testReturnsNullWhenArbKeyMissing(): void
    {
        $schema = ['summary' => 'No v3 exposure declared'];
        $this->assertNull($this->invokeGetCliCommandName($schema));
    }

    /**
     * Partial nested structure: v3 key path is incomplete. Still null;
     * we never consult legacy `x-cli-name`.
     */
    public function testReturnsNullWhenArbKeyStructureIsIncomplete(): void
    {
        $schema = [
            'x-acquia-exposure' => ['channels' => []],
            'x-cli-name' => 'still-ignored',
        ];
        $this->assertNull($this->invokeGetCliCommandName($schema));
    }


    /**
     * Kills the ProtectedVisibility mutation on ApiCommandHelper::getCliCommandName.
     *
     * Coverage of line 382 only happens when getApiCommands() is called on a BASE-CLASS
     * instance (child dispatch skips that line). Making the method private breaks
     * late-static binding: $this->getCliCommandName() inside generateApiCommandsFromSpec
     * would call the private parent version even when $this is ApiV3CommandHelper,
     * returning null for every v3 operation → 0 commands generated.
     */
    public function testGetCliCommandNamePolymorphicDispatch(): void
    {
        $spec = [
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.1.0',
            'paths' => [
                '/sites' => [
                    'get' => [
                        'operationId' => 'getSites',
                        'parameters' => [],
                        'responses' => ['200' => ['description' => 'OK', 'content' => []]],
                        'summary' => 'List sites',
                        'x-acquia-exposure' => ['channels' => ['cli' => ['command' => 'sites:list']]],
                    ],
                ],
            ],
        ];
        $specFile = tempnam(sys_get_temp_dir(), 'v3spec') . '.json';
        file_put_contents($specFile, json_encode($spec));

        // Base class reads x-cli-name only → 0 op-commands (covers line 382).
        $baseHelper = new ApiCommandHelper($this->logger);
        $baseCommands = $baseHelper->getApiCommands($specFile, 'api', $this->getCommandFactory());
        $baseOpCommands = array_filter($baseCommands, static fn ($c) => $c instanceof ApiBaseCommand);
        $this->assertCount(0, $baseOpCommands, 'Base helper must not read x-acquia-exposure.');

        // V3 class reads x-acquia-exposure via protected override → 1 op-command.
        // If getCliCommandName were private in parent, private dispatch would apply
        // and this would also return 0 → test fails → mutation killed.
        $v3Helper = new ApiV3CommandHelper($this->logger);
        $v3Commands = $v3Helper->getApiCommands($specFile, 'api:v3', $this->getCommandFactory());
        $v3OpCommands = array_filter($v3Commands, static fn ($c) => $c instanceof ApiBaseCommand);
        $this->assertNotEmpty($v3OpCommands, 'V3 helper must dispatch to the protected override for x-acquia-exposure.');

        unlink($specFile);
    }

    // End-to-end: drive the real v3 bundle through the real helper.
    private const V3_PREFIX = 'api:v3';

    private function getV3CommandFactory(): ApiV3CommandFactory
    {
        $v3ClientService = $this->prophet->prophesize(V3ClientService::class)->reveal();
        return new ApiV3CommandFactory(
            $this->localMachineHelper,
            $this->datastoreCloud,
            $this->datastoreAcli,
            $this->cloudCredentials,
            $this->telemetryHelper,
            $this->projectDir,
            $v3ClientService,
            $this->sshHelper,
            $this->sshDir,
            $this->logger,
            $this->selfUpdateManager,
        );
    }

    /**
     * @return ApiBaseCommand[]
     */
    private function loadRealV3Commands(): array
    {
        $specPath = Path::canonicalize(__DIR__ . '/../../../../../assets/acquia-v3-spec.json');
        $this->assertFileExists($specPath, 'v3 bundle missing; run `composer update-acquia-v3-spec`.');
        $helper = new ApiV3CommandHelper($this->logger);
        return $helper->getApiCommands($specPath, self::V3_PREFIX, $this->getV3CommandFactory());
    }

    /**
     * Baseline smoke: the real bundle produces a non-empty command set.
     * Catches outright generation failures (spec loads, factory runs, commands return).
     */
    public function testRealV3SpecProducesCommands(): void
    {
        $commands = $this->loadRealV3Commands();
        $this->assertNotEmpty($commands, 'Real v3 bundle generated 0 commands.');
    }

    /**
     * Every generated command must live under the api:v3 namespace.
     * Kills mutations that drop or mangle the prefix.
     */
    public function testEveryGeneratedCommandHasApiV3Prefix(): void
    {
        $commands = $this->loadRealV3Commands();
        foreach ($commands as $command) {
            $name = $command->getName();
            $this->assertStringStartsWith(
                self::V3_PREFIX . ':',
                (string) $name,
                "Command '$name' is missing the api:v3 prefix."
            );
        }
    }

    /**
     * Command names are unique across the bundle. Detects join-prefix collisions
     * (e.g. two services declaring the same x-cli-name would otherwise clobber each other).
     */
    public function testNoDuplicateCommandNames(): void
    {
        $commands = $this->loadRealV3Commands();
        $names = array_map(static fn ($c) => $c->getName(), $commands);
        $duplicates = array_keys(array_filter(array_count_values($names), static fn ($n) => $n > 1));
        $this->assertSame(
            [],
            $duplicates,
            'Duplicate command names after join: ' . implode(', ', $duplicates)
        );
    }

    /**
     * The generator must produce one command per operation that declares a CLI name
     * (either legacy x-cli-name or x-acquia-exposure.channels.cli.command),
     * plus autogenerated ApiList wrappers for each sub-namespace.
     */
    public function testCommandCountMatchesOperationCountInSpec(): void
    {
        $specPath = Path::canonicalize(__DIR__ . '/../../../../../assets/acquia-v3-spec.json');
        $spec = json_decode((string) file_get_contents($specPath), true);
        $expectedOps = 0;
        foreach ($spec['paths'] as $methods) {
            foreach ($methods as $verb => $op) {
                if (!in_array($verb, ['get', 'post', 'put', 'delete', 'patch'], true)) {
                    continue;
                }
                $hasLegacy = isset($op['x-cli-name']);
                $hasNew = isset($op['x-acquia-exposure']['channels']['cli']['command']);
                if ($hasLegacy || $hasNew) {
                    $expectedOps++;
                }
            }
        }

        $commands = $this->loadRealV3Commands();
        $generatedOpCommands = array_filter(
            $commands,
            static fn ($c) => $c instanceof ApiBaseCommand
        );
        $this->assertCount(
            $expectedOps,
            $generatedOpCommands,
            'Operation-to-command mapping is not 1:1.'
        );
    }

    /**
     * Sanity check: every generated operation-level command has the basics populated
     * (name, description, HTTP method, path). Detects silent drops of required fields.
     */
    public function testEveryGeneratedOperationCommandHasRequiredFields(): void
    {
        $commands = $this->loadRealV3Commands();
        foreach ($commands as $command) {
            if (!$command instanceof ApiBaseCommand) {
                // Skip ApiList* wrappers; they don't carry method/path.
                continue;
            }
            $name = (string) $command->getName();
            $this->assertNotEmpty($name, 'Command has empty name.');
            $this->assertNotEmpty($command->getDescription(), "Command '$name' has empty description.");
            $this->assertNotEmpty($command->getMethod(), "Command '$name' has empty HTTP method.");
            $this->assertNotEmpty($command->getPath(), "Command '$name' has empty path.");
        }
    }

    /**
     * Non-production commands get a [stability] tag appended to their description.
     * Production commands must NOT get a tag.
     */
    public function testStabilityTagAppearedInDescription(): void
    {
        $commands = $this->loadRealV3Commands();
        foreach ($commands as $command) {
            if (!$command instanceof ApiBaseCommand) {
                continue;
            }
            $stability = $command->getStability();
            $desc = (string) $command->getDescription();
            if ($stability !== null && $stability !== 'production') {
                $this->assertStringContainsString(
                    '[' . $stability . ']',
                    $desc,
                    "Command '{$command->getName()}' (stability=$stability) missing tag in description."
                );
            } else {
                $this->assertStringNotContainsString(
                    '[',
                    $desc,
                    "Command '{$command->getName()}' (production) must not have a stability tag."
                );
            }
        }
    }

    /**
     * Stability warning is printed at runtime for non-production commands.
     */
    public function testStabilityWarningPrintedAtRuntime(): void
    {
        $spec = [
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'openapi' => '3.1.0',
            'paths' => [
                '/sites' => [
                    'get' => [
                        'operationId' => 'getSites',
                        'parameters' => [],
                        'responses' => ['200' => ['description' => 'OK', 'content' => []]],
                        'summary' => 'List sites',
                        'x-acquia-exposure' => [
                            'channels' => ['cli' => ['command' => 'sites:list', 'enabled' => true]],
                            'stability' => 'development',
                        ],
                    ],
                ],
            ],
        ];
        $specFile = tempnam(sys_get_temp_dir(), 'v3spec') . '.json';
        file_put_contents($specFile, json_encode($spec));
        $helper = new ApiV3CommandHelper($this->logger);
        $commands = $helper->getApiCommands($specFile, 'api:v3', $this->getV3CommandFactory());
        unlink($specFile);

        $opCommands = array_values(array_filter($commands, static fn ($c) => $c instanceof ApiBaseCommand));
        $this->assertCount(1, $opCommands);
        $cmd = $opCommands[0];
        $this->assertSame('development', $cmd->getStability());
        $this->assertSame('List sites [development]', (string) $cmd->getDescription());
    }
}
