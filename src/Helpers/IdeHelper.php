<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use loophp\phposinfo\OsInfo;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Class LocalMachineHelper.
 *
 * A helper for executing commands on the local client. A wrapper for 'exec' and 'passthru'.
 *
 * @package Acquia\Cli\Helpers
 */
class IdeHelper {

  /**
   * Determines if Acquia CLI is being run from within a Cloud IDE.
   *
   * @return bool
   *   TRUE if Acquia CLI is being run from within a Cloud IDE.
   */
  public static function isAcquiaCloudIde(): bool {
    return AcquiaDrupalEnvironmentDetector::isAhIdeEnv();
  }

  /**
   * Get the Cloud Application UUID from a Cloud IDE's environmental variable.
   *
   * This command assumes it is being run inside of a Cloud IDE.
   *
   * @return array|false|string
   */
  public static function getThisCloudIdeCloudAppUuid() {
    return getenv('ACQUIA_APPLICATION_UUID');
  }

  /**
   * Get the UUID from a Cloud IDE's environmental variable.
   *
   * This command assumes it is being run inside a Cloud IDE.
   *
   * @return false|string
   */
  public static function getThisCloudIdeUuid() {
    return getenv('REMOTEIDE_UUID');
  }

  /**
   * @throws AcquiaCliException
   */
  public function requireCloudIdeEnvironment(): void {
    if (!self::isAcquiaCloudIde() || !self::getThisCloudIdeUuid()) {
      throw new AcquiaCliException('This command can only be run inside of an Acquia Cloud IDE');
    }
  }

}
