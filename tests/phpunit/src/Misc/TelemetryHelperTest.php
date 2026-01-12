<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Tests\TestBase;

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

    /**
     * @group serial
     * @dataProvider providerTestEnvironmentProvider()
     */
    public function testEnvironmentProvider(string $provider, array $envVars): void
    {
        $this->unsetGitHubEnvVars();
        TestBase::setEnvVars($envVars);
        $this->assertEquals($provider, TelemetryHelper::getEnvironmentProvider());
    }

    /**
     * Test the getEnvironmentProvider method when no environment provider is
     * detected.
     *
     * @group serial
     */
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
     * @dataProvider providerTestAhEnvNormalization
     * @param string $ah_env
     *   The Acquia hosting environment.
     * @param string $expected
     *   The expected normalized environment.
     * @group serial
     */
    public function testAhEnvNormalization(string $ah_env, string $expected): void
    {
        $normalized_ah_env = TelemetryHelper::normalizeAhEnv($ah_env);
        $this->assertEquals($expected, $normalized_ah_env);
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

    public function testIsBuildDateOlderThanMonthsEdgeCase(): void
    {
        $now = strtotime('2026-01-12');
        $buildDate = date('Y-m-d', strtotime('-3 months', $now));
        // Should be false if exactly 3 months ago (not older)
        $this->assertFalse(TelemetryHelper::isBuildDateOlderThanMonths($buildDate, 3, $now));
    }
}
