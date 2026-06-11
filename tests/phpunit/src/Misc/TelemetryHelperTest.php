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

    public function unsetGitHubEnvVars(): void
    {
        $providers = TelemetryHelper::getProviders();

        // Since we actually run our own tests on GitHub, getEnvironmentProvider() will return 'github' unless we unset it.
        $github_env_vars = [];
        foreach ($providers['github'] as $var) {
            $github_env_vars[$var] = self::ENV_VAR_DEFAULT_VALUE;
        }
        TestBase::unsetEnvVars($github_env_vars);
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
        $this->unsetGitHubEnvVars();
        TestBase::setEnvVars($envVars);
        $this->assertEquals($provider, TelemetryHelper::getEnvironmentProvider());
    }

    /**
     * Test the getEnvironmentProvider method when no environment provider is
     * detected.
     */
    #[Group('serial')]
    public function testGetEnvironmentProviderWithoutAnyEnvSet(): void
    {
        $this->unsetGitHubEnvVars();

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
