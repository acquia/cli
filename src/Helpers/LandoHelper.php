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
class LandoHelper {

  public static function getLandoInfo() {
    if ($lando_info = AcquiaDrupalEnvironmentDetector::getLandoInfo()) {
      return json_decode($lando_info);
    }
    return NULL;
  }

  public static function isLandoEnv() {
    return (bool) self::getLandoInfo();
  }

}
