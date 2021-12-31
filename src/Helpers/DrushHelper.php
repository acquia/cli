<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
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
class DrushHelper {

  /**
   * @param null $output_callback
   *
   * @return bool
   */
  protected function getDrushDatabaseConnectionStatus($output_callback = NULL): bool {
    if (!is_null($this->drushHasActiveDatabaseConnection)) {
      return $this->drushHasActiveDatabaseConnection;
    }
    if ($this->localMachineHelper->commandExists('drush')) {
      $process = $this->localMachineHelper->execute([
        'drush',
        'status',
        '--fields=db-status,drush-version',
        '--format=json',
        '--no-interaction',
      ], $output_callback, $this->dir, FALSE);
      if ($process->isSuccessful()) {
        $drush_status_return_output = json_decode($process->getOutput(), TRUE);
        if (is_array($drush_status_return_output) && array_key_exists('db-status', $drush_status_return_output) && $drush_status_return_output['db-status'] === 'Connected') {
          $this->drushHasActiveDatabaseConnection = TRUE;
          return $this->drushHasActiveDatabaseConnection;
        }
      }
    }

    $this->drushHasActiveDatabaseConnection = FALSE;

    return $this->drushHasActiveDatabaseConnection;
  }
}