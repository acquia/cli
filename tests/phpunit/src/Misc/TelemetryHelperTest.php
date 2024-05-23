<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;

class TelemetryHelperTest extends CommandTestBase {

  public function tearDown(): void {
    parent::tearDown();
    $envVars = ['AH_SITE_ENVIRONMENT' => 'test'];
    foreach ($this->providerTestEnvironmentProvider() as $args) {
      $envVars = array_merge($envVars, $args[1]);
    }

    TestBase::unsetEnvVars($envVars);
  }

  protected function createCommand(): CommandBase {
    return $this->injectCommand(ClearCacheCommand::class);
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
        $env_vars_with_values[$var_name] = 'test';
      }
      $providersArray[] = [$provider, $env_vars_with_values];
    }

    return $providersArray;
  }

  /**
   * @dataProvider providerTestEnvironmentProvider()
   */
  public function testEnvironmentProvider(string $provider, array $envVars): void {
    TestBase::setEnvVars($envVars);
    $this->assertEquals($provider, TelemetryHelper::getEnvironmentProvider());
  }

  /**
   * Test the getEnvironmentProvider method when no environment provider is detected.
   */
  public function testGetEnvironmentProviderWithoutAnyEnvSet(): void {
    // Expect null since no provider environment variables are set.
    $this->assertNull(TelemetryHelper::getEnvironmentProvider());
  }

  /**
   * Test the getEnvironmentProvider method when Acquia environment is detected.
   */
  public function testGetEnvironmentProviderWithAcquia(): void {
    TestBase::setEnvVars(['AH_SITE_ENVIRONMENT' => 'test']);

    // We need to make sure our mocked method is used. Depending on the implementation,
    // this could involve setting it statically or using dependency injection.
    // Expect 'acquia' to be returned since Acquia environment is mocked to be present.
    $this->assertEquals('acquia', TelemetryHelper::getEnvironmentProvider());
  }

}
