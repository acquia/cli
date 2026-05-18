<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Command\Api\ApiV3CommandHelper;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionMethod;
use Symfony\Component\Filesystem\Path;

/**
 * Tests for ApiV3CommandHelper::getCliCommandName — covers the ARB-550
 * extension-key migration window (x-acquia-exposure.channels.cli.command
 * replacing the legacy x-cli-name).
 *
 * @see https://github.com/acquia/architecture-decisions/blob/master/openspec/specs/api-specification/spec.md
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
     * Migrated service: ARB-550 key is present. Helper reads it.
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
     * the operation is skipped in the v3 namespace. The composer bundling
     * pipeline is responsible for emitting ARB-550-shaped specs to v3.
     */
    public function testIgnoresLegacyXCliNameKey(): void
    {
        $schema = ['x-cli-name' => 'legacy-ignored'];
        $this->assertNull($this->invokeGetCliCommandName($schema));
    }

    /**
     * Both keys present: ARB-550 key is read, legacy is ignored entirely.
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
        $schema = ['summary' => 'No ARB exposure declared'];
        $this->assertNull($this->invokeGetCliCommandName($schema));
    }

    /**
     * Partial nested structure: ARB-key path is incomplete. Still null;
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

    private function invokeNormalizePath(string $path): string
    {
        $helper = new ApiV3CommandHelper($this->logger);
        $ref = new ReflectionMethod(ApiV3CommandHelper::class, 'normalizePath');
        return $ref->invoke($helper, $path);
    }

    /**
     * Env-service paths must be routed through `/v3/` on the live gateway.
     */
    public function testNormalizePathPrependsV3ForEnvironmentPaths(): void
    {
        $this->assertSame(
            '/v3/environments/{environmentId}',
            $this->invokeNormalizePath('/environments/{environmentId}')
        );
    }

    /**
     * Site-instance paths live at `/api/site-instances/...` on the live
     * gateway, NOT behind `/v3/`. The helper must leave them untouched.
     */
    public function testNormalizePathLeavesSiteInstancePathsUntouched(): void
    {
        $this->assertSame(
            '/site-instances/{siteId}.{environmentId}',
            $this->invokeNormalizePath('/site-instances/{siteId}.{environmentId}')
        );
    }

    public function testNormalizePathPrependsV3ForDeploymentPaths(): void
    {
        $this->assertSame(
            '/v3/deployments/{deploymentId}',
            $this->invokeNormalizePath('/deployments/{deploymentId}')
        );
    }

    /**
     * Site-service paths live at `/api/sites/...` on the live gateway,
     * NOT behind `/v3/`. The helper must leave them untouched.
     */
    public function testNormalizePathLeavesSiteServicePathsUntouched(): void
    {
        $this->assertSame(
            '/sites/{siteId}/actions/duplicate',
            $this->invokeNormalizePath('/sites/{siteId}/actions/duplicate')
        );
    }

    // End-to-end: drive the real v3 bundle through the real helper.
    private const V3_PREFIX = 'api:v3';

    /**
     * @return ApiBaseCommand[]
     */
    private function loadRealV3Commands(): array
    {
        $specPath = Path::canonicalize(__DIR__ . '/../../../../../assets/acquia-v3-spec.json');
        $this->assertFileExists($specPath, 'v3 bundle missing; run `composer update-acquia-v3-spec`.');
        $helper = new ApiV3CommandHelper($this->logger);
        return $helper->getApiCommands($specPath, self::V3_PREFIX, $this->getCommandFactory());
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
     * (either legacy x-cli-name or ARB-550 x-acquia-exposure.channels.cli.command),
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
}
