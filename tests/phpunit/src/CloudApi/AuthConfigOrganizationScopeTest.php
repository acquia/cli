<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AuthConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Separate from AuthConfigTest, which is in the serial group that Infection
 * excludes. This parsing takes its input as an argument, so it needs no
 * environment and can live where every run exercises it.
 */
class AuthConfigOrganizationScopeTest extends TestCase
{
    public function testAUuidBecomesAnOrganizationScope(): void
    {
        $this->assertSame(
            'organization:17feaf34-5d04-4d0b-9f0b-aaaaaaaaaaaa',
            AuthConfig::organizationScope('17feaf34-5d04-4d0b-9f0b-aaaaaaaaaaaa')
        );
    }

    public function testSurroundingWhitespaceIsIgnored(): void
    {
        $this->assertSame(
            'organization:17feaf34-5d04-4d0b-9f0b-aaaaaaaaaaaa',
            AuthConfig::organizationScope("  17feaf34-5d04-4d0b-9f0b-aaaaaaaaaaaa\n")
        );
    }

    public function testUppercaseIsAccepted(): void
    {
        $this->assertSame(
            'organization:17FEAF34-5D04-4D0B-9F0B-AAAAAAAAAAAA',
            AuthConfig::organizationScope('17FEAF34-5D04-4D0B-9F0B-AAAAAAAAAAAA')
        );
    }

    /**
     * @return array<string, array{0: string|false}>
     */
    public static function valuesThatYieldNoScope(): array
    {
        return [
            'empty' => [''],
            'not a uuid' => ['my-organization'],
            // Space-delimited, so a value carrying its own scope must not pass.
            'scope injection' => ['17feaf34-5d04-4d0b-9f0b-aaaaaaaaaaaa admin:all'],
            'unset' => [false],
            'uuid with a trailing segment' => ['17feaf34-5d04-4d0b-9f0b-aaaaaaaaaaaa-extra'],
            'whitespace only' => ['   '],
        ];
    }

    /**
     * A developer who is not on federated auth, or who exports something
     * malformed, must still be able to sign in.
     */
    #[DataProvider('valuesThatYieldNoScope')]
    public function testAnythingOtherThanAUuidYieldsNoScope(string|false $value): void
    {
        $this->assertSame('', AuthConfig::organizationScope($value));
    }
}
