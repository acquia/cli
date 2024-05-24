<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Tests\TestBase;

class TelemetryHelperTest extends TestBase {

  const ENV_VAR_DEFAULT_VALUE = 'test';

  public function tearDown(): void {
    parent::tearDown();
    $envVars = [];
    foreach ($this->providerTestEnvironmentProvider() as $args) {
      $envVars = array_merge($envVars, $args[1]);
    }

    TestBase::unsetEnvVars($envVars);
  }

  public function unsetGitHubEnvVars(): void {
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
  public function providerTestEnvironmentProvider(): array {
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
   * @dataProvider providerTestEnvironmentProvider()
   */
  public function testEnvironmentProvider(string $provider, array $envVars): void {
    $this->unsetGitHubEnvVars();
    TestBase::setEnvVars($envVars);
    $this->assertEquals($provider, TelemetryHelper::getEnvironmentProvider());
  }

  /**
   * Test the getEnvironmentProvider method when no environment provider is detected.
   */
  public function testGetEnvironmentProviderWithoutAnyEnvSet(): void {
    $this->unsetGitHubEnvVars();

    // Expect null since no provider environment variables are set.
    $this->assertNull(TelemetryHelper::getEnvironmentProvider());
  }
  public function providerTestAhEnvNormalization(): array {
    return [
      ['prod', 'prod'],
      ['01live', 'prod'],
      ['stage', 'stage'],
      ['stg', 'stage'],
      ['dev1', 'dev'],
      ['ode1', 'ode'],
      ['ide', 'ide'],
    ];
  }

  /**
   * @dataProvider providerTestAhEnvNormalization
   */
  public function testAhEnvNormalization($ah_env, $expected) {
    $normalized_ah_env = TelemetryHelper::normalizeAhEnv($ah_env);
    $this->assertEquals($expected, $normalized_ah_env);
  }

}
