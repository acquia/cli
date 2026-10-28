<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Tests\TestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

class TelemetryHelperTest extends TestBase
{
    protected const ENV_VAR_DEFAULT_VALUE = 'test';

    public function tearDown(): void
    {
        parent::tearDown();
        $envVars = [];
        foreach (self::providerTestEnvironmentProvider() as $args) {
            $envVars = array_merge($envVars, $args[1]);
        }

        TestBase::unsetEnvVars($envVars);
    }

    /**
     * Clears every provider's variables so detection starts from nothing.
     */
    public function unsetAllProviderEnvVars(): void
    {
        $envVars = [];
        foreach (TelemetryHelper::getProviders() as $vars) {
            foreach ($vars as $var) {
                $envVars[$var] = self::ENV_VAR_DEFAULT_VALUE;
            }
        }
        TestBase::unsetEnvVars($envVars);
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestEnvironmentProvider(): array
    {
        $providersList = TelemetryHelper::getProviders();
        $providersArray = [];
        foreach ($providersList as $provider => $envVars) {
            $env_vars_with_values = [];
            foreach ($envVars as $var_name) {
                $env_vars_with_values[$var_name] = self::ENV_VAR_DEFAULT_VALUE;
            }
            $providersArray[] = [$provider, $env_vars_with_values];
        }

        return $providersArray;
    }

    #[DataProvider('providerTestEnvironmentProvider')]
    #[Group('serial')]
    public function testEnvironmentProvider(string $provider, array $envVars): void
    {
        $this->unsetAllProviderEnvVars();
        TestBase::setEnvVars($envVars);
        $this->assertEquals($provider, TelemetryHelper::getEnvironmentProvider());
    }

    /**
     * Pins the agent harnesses' own variable names.
     *
     * testEnvironmentProvider() reads the names out of getProviders(), so it
     * passes whatever they say — a typo there detects nothing and still goes
     * green. These are asserted literally so a rename has to be deliberate.
     */
    public function testAgentHarnessEnvVarNames(): void
    {
        $providers = TelemetryHelper::getProviders();

        // Claude Code exports CLAUDECODE (no underscore) into every shell it spawns.
        $this->assertSame(
            ['CLAUDECODE', 'CLAUDE_CODE_ENTRYPOINT', 'CLAUDE_CODE_SESSION_ID'],
            $providers['claude-code']
        );
        // CURSOR_AGENT marks an agent-run shell; CURSOR_TRACE_ID is also set in a
        // human's Cursor terminal, which the spec counts as the same harness.
        $this->assertSame(['CURSOR_AGENT', 'CURSOR_TRACE_ID'], $providers['cursor']);
    }


    /**
     * Test the getEnvironmentProvider method when no environment provider is
     * detected.
     */
    #[Group('serial')]
    public function testGetEnvironmentProviderWithoutAnyEnvSet(): void
    {
        $this->unsetAllProviderEnvVars();

        // Expect null since no provider environment variables are set.
        $this->assertNull(TelemetryHelper::getEnvironmentProvider());
    }

    /**
     * @return mixed[]
     *   The data provider.
     */
    public static function providerTestAhEnvNormalization(): array
    {
        return [
            ['prod', 'prod'],
            ['01live', 'prod'],
            ['stage', 'stage'],
            ['stg', 'stage'],
            ['dev1', 'dev'],
            ['ode1', 'ode'],
            ['ide', 'ide'],
            ['fake', 'fake'],
        ];
    }

    /**
     * @param string $ah_env
     *   The Acquia hosting environment.
     * @param string $expected
     *   The expected normalized environment.
     */
    #[DataProvider('providerTestAhEnvNormalization')]
    #[Group('serial')]
    public function testAhEnvNormalization(string $ah_env, string $expected): void
    {
        $normalized_ah_env = TelemetryHelper::normalizeAhEnv($ah_env);
        $this->assertEquals($expected, $normalized_ah_env);
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestRedactSensitiveData(): array
    {
        return [
            [['key' => 'mykey'], ['key' => 'REDACTED']],
            [['secret' => 'mysecret'], ['secret' => 'REDACTED']],
            [['password' => 'mypassword'], ['password' => 'REDACTED']],
            [['token' => 'mytoken'], ['token' => 'REDACTED']],
            [['api-key' => 'mykey'], ['api-key' => 'REDACTED']],
            [['api-secret' => 'mysecret'], ['api-secret' => 'REDACTED']],
            // Unset (null) values should not be redacted, lest it appear
            // that a value was actually passed.
            [['key' => null], ['key' => null]],
            // Non-sensitive values should be left untouched.
            [
                ['filename' => 'id_rsa', 'password' => 'foo'],
                ['filename' => 'id_rsa', 'password' => 'REDACTED'],
            ],
            // Sensitive keys nested in array values should be redacted.
            [
                ['params' => ['password' => 'foo', 'filename' => 'id_rsa']],
                ['params' => ['password' => 'REDACTED', 'filename' => 'id_rsa']],
            ],
        ];
    }

    /**
     * @param array<mixed> $data
     * @param array<mixed> $expected
     */
    #[DataProvider('providerTestRedactSensitiveData')]
    public function testRedactSensitiveData(array $data, array $expected): void
    {
        $this->assertSame($expected, TelemetryHelper::redactSensitiveData($data));
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestRedactSensitiveContext(): array
    {
        return [
            ['ssh-key:create --password=foo', 'ssh-key:create --passwordREDACTED'],
            ['auth:login --key=foo --secret=bar', 'auth:login --keyREDACTED'],
            ['auth:login --secret=bar', 'auth:login --secretREDACTED'],
            ['app:link myapp', 'app:link myapp'],
        ];
    }

    #[DataProvider('providerTestRedactSensitiveContext')]
    public function testRedactSensitiveContext(string $context, string $expected): void
    {
        $this->assertSame($expected, TelemetryHelper::redactSensitiveContext($context));
    }

    /**
     * Each row is the raw output one platform's machine ID command produces.
     *
     * @return array<string, array{?string, ?string}>
     */
    public static function providerTestSanitizeMachineId(): array
    {
        return [
            'bsd kenv' => ["00000000-1111-2222-3333-444444444444\n", '00000000-1111-2222-3333-444444444444'],
            'command failed' => [null, null],
            'command printed nothing' => ["   \n", null],
            'interior newline' => ["abc\ndef", null],
            'interior space' => ["not an id\n", null],
            // A boundary probe, not a realistic pair of IDs: the ceiling only
            // has to reject output that no branch above managed to parse.
            'length ceiling, at' => [str_repeat('a', 64), str_repeat('a', 64)],
            'length ceiling, past' => [str_repeat('a', 65), null],
            // Without a machine-id file the command falls back to `hostname`,
            // which is shorter than an ID and may be qualified.
            'linux hostname (container)' => ["0123456789ab\n", '0123456789ab'],
            'linux hostname (fqdn)' => ["runner-07.ci.example.com\n", 'runner-07.ci.example.com'],
            'linux hostname (short)' => ["build-runner-07\n", 'build-runner-07'],
            // `( cat ...machine-id || hostname ) | head -n 1` keeps the newline.
            'linux machine-id' => ["0123456789abcdef0123456789abcdef\n", '0123456789abcdef0123456789abcdef'],
            // macOS is the only platform phposinfo parses for us.
            'macos already parsed' => ['aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
            'windows braced guid' => ["{00000000-1111-2222-3333-444444444444}\n", '00000000-1111-2222-3333-444444444444'],
            // A locked-down box answers with a message, not a value.
            'windows error output' => [
                "\r\nERROR: The system was unable to find the specified registry key or value.\r\n",
                null,
            ],
            // Matched case-insensitively, so alternate tooling still parses.
            'windows lowercase key' => [
                "\r\n    machineguid    reg_sz    00000000-1111-2222-3333-444444444444\r\n",
                '00000000-1111-2222-3333-444444444444',
            ],
            'windows reg query' => [
                "\r\nHKEY_LOCAL_MACHINE\\SOFTWARE\\Microsoft\\Cryptography\r\n    MachineGuid    REG_SZ    00000000-1111-2222-3333-444444444444\r\n\r\n",
                '00000000-1111-2222-3333-444444444444',
            ],
            // A 32-bit process reads the redirected key. The value line is
            // matched, not the key path, so the redirection does not matter.
            'windows wow6432node' => [
                "\r\nHKEY_LOCAL_MACHINE\\SOFTWARE\\Wow6432Node\\Microsoft\\Cryptography\r\n    MachineGuid    REG_SZ    00000000-1111-2222-3333-444444444444\r\n\r\n",
                '00000000-1111-2222-3333-444444444444',
            ],
        ];
    }

    #[DataProvider('providerTestSanitizeMachineId')]
    public function testSanitizeMachineId(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, TelemetryHelper::sanitizeMachineId($raw));
    }

    public function testIsBuildDateOlderThanMonthsNullDate(): void
    {
        $this->assertFalse(TelemetryHelper::isBuildDateOlderThanMonths(null, 3));
    }

    public function testIsBuildDateOlderThanMonthsInvalidDate(): void
    {
        $this->assertFalse(TelemetryHelper::isBuildDateOlderThanMonths('not-a-date', 3));
    }

    public function testIsBuildDateOlderThanMonthsRecentDate(): void
    {
        $now = strtotime('2026-01-12');
        $buildDate = date('Y-m-d', strtotime('-2 months', $now));
        $this->assertFalse(TelemetryHelper::isBuildDateOlderThanMonths($buildDate, 3, $now));
    }

    public function testIsBuildDateOlderThanMonthsOldDate(): void
    {
        $now = strtotime('2026-01-12');
        $buildDate = date('Y-m-d', strtotime('-4 months', $now));
        $this->assertTrue(TelemetryHelper::isBuildDateOlderThanMonths($buildDate, 3, $now));
    }
}
