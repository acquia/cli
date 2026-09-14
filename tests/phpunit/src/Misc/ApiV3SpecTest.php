<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

/**
 * Structural sanity checks on the bundled v3 OpenAPI spec
 * (assets/acquia-v3-spec.json, produced by `composer update-acquia-v3-spec`).
 * Complements ApiSpecTest which covers the v2 bundle.
 */
class ApiV3SpecTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function loadSpec(): array
    {
        $path = Path::canonicalize(__DIR__ . '/../../../../assets/acquia-v3-spec.json');
        self::assertFileExists($path, 'v3 spec missing; run `composer update-acquia-v3-spec`.');
        $decoded = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($decoded, 'v3 spec is not valid JSON.');
        return $decoded;
    }

    public function testSpecFileExistsAndIsValidJson(): void
    {
        $spec = self::loadSpec();
        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
    }

    public function testOpenApiVersionIsAtLeast31(): void
    {
        $spec = self::loadSpec();
        $this->assertMatchesRegularExpression('/^3\.(1|[2-9])/', $spec['openapi']);
    }

    public function testSpecHasAtLeastOnePath(): void
    {
        $spec = self::loadSpec();
        $this->assertNotEmpty($spec['paths']);
    }

    public function testSpecDoesNotLeakInternalArtifacts(): void
    {
        $raw = (string) file_get_contents(
            Path::canonicalize(__DIR__ . '/../../../../assets/acquia-v3-spec.json')
        );
        $this->assertStringNotContainsString('x-internal', $raw);
        $this->assertStringNotContainsString('cloud.acquia.dev', $raw);
        $this->assertStringNotContainsString('%specUrl%', $raw, 'Preprocessor did not resolve all placeholders.');
    }

    public function testEveryOperationDeclaresACliCommandName(): void
    {
        $spec = self::loadSpec();
        $missing = [];
        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $verb => $operation) {
                if (!in_array($verb, ['get', 'post', 'put', 'delete', 'patch'], true)) {
                    continue;
                }
                $hasLegacy = isset($operation['x-cli-name']);
                $hasNew = isset($operation['x-acquia-exposure']['channels']['cli']['command']);
                if (!$hasLegacy && !$hasNew) {
                    $missing[] = strtoupper($verb) . ' ' . $path;
                }
            }
        }
        $this->assertEmpty(
            $missing,
            'Operations in v3 bundle have no CLI command name (x-cli-name or x-acquia-exposure.channels.cli.command): '
                . implode(', ', $missing)
        );
    }

    public function testNoResidualJsonRefsAfterDereferencing(): void
    {
        $spec = self::loadSpec();
        $residual = self::collectRefs($spec);
        $this->assertSame(
            [],
            $residual,
            'Bundle still contains $ref entries after --dereferenced: ' . implode(', ', array_slice($residual, 0, 5))
        );
    }

    /**
     * @return string[]
     */
    private static function collectRefs(mixed $node, string $path = ''): array
    {
        $out = [];
        if (!is_array($node)) {
            return $out;
        }
        foreach ($node as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                $out[] = $path . '/$ref=' . $value;
            }
            if (is_array($value)) {
                $out = array_merge($out, self::collectRefs($value, $path . '/' . $key));
            }
        }
        return $out;
    }
}
