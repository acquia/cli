<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Tests\TestBase;

class IdeHelper {

  public static string $remote_ide_uuid = '215824ff-272a-4a8c-9027-df32ed1d68a9';

  public static function setCloudIdeEnvVars(): void {
    TestBase::setEnvVars(self::getEnvVars());
  }

  public static function unsetCloudIdeEnvVars(): void {
    TestBase::unsetEnvVars(self::getEnvVars());
  }

  public static function getEnvVars(): array {
    return [
      'ACQUIA_USER_UUID' => '4acf8956-45df-3cf4-5106-065b62cf1ac8',
      'AH_SITE_ENVIRONMENT' => 'IDE',
      'REMOTEIDE_UUID' => self::$remote_ide_uuid,
    ];
  }

}
