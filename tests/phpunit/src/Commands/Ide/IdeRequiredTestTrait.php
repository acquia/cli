<?php

namespace Acquia\Cli\Tests\Commands\Ide;

/**
 * Class IdeRequiredTestBase.
 */
trait IdeRequiredTestTrait {

  /**
   * @var string
   */
  public static $remote_ide_uuid = '4ba6c569-5084-4b6d-9467-019ccb5dc847';

  /**
   * @var string
   */
  public static $application_uuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

  /**
   * This method is called before each test.
   *
   * @param null $output
   *
   */
  public function setUp($output = NULL): void {
    parent::setUp();
    self::setCloudIdeEnvVars();
  }

  protected function tearDown(): void {
    parent::tearDown();
    self::unsetCloudIdeEnvVars();
  }

  public static function getCloudIdeEnvVars(): array {
    return [
      'REMOTEIDE_UUID' => self::$remote_ide_uuid,
      'ACQUIA_APPLICATION_UUID' => self::$application_uuid,
      'ACQUIA_USER_UUID' => '4acf8956-45df-3cf4-5106-065b62cf1ac8',
      'AH_SITE_ENVIRONMENT' => 'IDE',
    ];
  }

  public static function setCloudIdeEnvVars(): void {
    foreach (self::getCloudIdeEnvVars() as $key => $value) {
      putenv($key . '=' . $value);
    }
  }

  public static function unsetCloudIdeEnvVars(): void {
    foreach (self::getCloudIdeEnvVars() as $key => $value) {
      putenv($key);
    }
  }

}
