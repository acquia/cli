<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Tests\TestBase;

/**
 * Class IdeRequiredTestBase.
 */
trait IdeRequiredTestTrait {

  /**
   * @var string
   */
  public static $remote_ide_uuid = '215824ff-272a-4a8c-9027-df32ed1d68a9';

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

  public function tearDown(): void {
    parent::tearDown();
    self::unsetCloudIdeEnvVars();
  }

  public static function setCloudIdeEnvVars() {
    TestBase::setEnvVars(self::getEnvVars());
  }

  public static function unsetCloudIdeEnvVars() {
    TestBase::unsetEnvVars(self::getEnvVars());
  }

  public static function getEnvVars(): array {
    return [
      'REMOTEIDE_UUID' => self::$remote_ide_uuid,
      'ACQUIA_USER_UUID' => '4acf8956-45df-3cf4-5106-065b62cf1ac8',
      'AH_SITE_ENVIRONMENT' => 'IDE',
    ];
  }

}
