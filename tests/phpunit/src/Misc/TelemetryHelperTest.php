<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Tests\TestBase;

class TelemetryHelperTest extends TestBase {

  const ENV_VAR_DEFAULT_VALUE = 'test';

  public function tearDown(): void {
    parent::tearDown();
    $envVars = ['AH_SITE_ENVIRONMENT' => 'test'];
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

  /**
   * Test the getEnvironmentProvider method when Acquia environment is detected.
   */
  public function testGetEnvironmentProviderWithAcquia(): void {
    // We test this separately from testEnvironmentProvider() because AH_SITE_ENVIRONMENT isn't in
    // TelemetryHelper::getProviders(). Instead, we rely on AcquiaDrupalEnvironmentDetector::getAhEnv() in
    // getEnvironmentProvider() to indirectly tell us if AH_SITE_ENVIRONMENT is set. This allows
    // AcquiaDrupalEnvironmentDetector to handle any changes to the logic of detecting Acquia environments.
    TestBase::setEnvVars(['AH_SITE_ENVIRONMENT' => self::ENV_VAR_DEFAULT_VALUE]);
    $this->assertEquals('acquia', TelemetryHelper::getEnvironmentProvider());
  }

}
