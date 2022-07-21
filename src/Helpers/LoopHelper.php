<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Output\Spinner\Spinner;
use React\EventLoop\Loop;

class LoopHelper {

  public static function getLoopy($output, $io, $spinnerMessage, $statusCallback, $successCallback, $timeoutCallback = NULL): void {
    $timers = [];
    $spinner = new Spinner($output, 4);
    $spinner->setMessage($spinnerMessage);
    $spinner->start();

    // Spinner timer.
    $timers[] = Loop::addPeriodicTimer($spinner->interval(),
      static function () use ($spinner) {
        $spinner->advance();
      });

    // Primary timer checking for result status.
    $timers[] = Loop::addPeriodicTimer(5, static function () use (&$timers, $spinner, $io, $statusCallback, $successCallback) {
      try {
        if ($statusCallback) {
          foreach ($timers as $timer) {
            Loop::cancelTimer($timer);
          }
          $timers = [];
          $spinner->finish();
          $successCallback();
        }
      }
      catch (\Exception $e) {
        $io->error($e->getMessage());
      }
    });

    // Watchdog timer.
    $timers[] = Loop::addTimer(45 * 60, static function () use ($spinner, &$timers, $timeoutCallback, $io) {
      foreach ($timers as $timer) {
        Loop::cancelTimer($timer);
      }
      $timers = [];
      $spinner->finish();
      $io->error("Timed out after 45 minutes!");
      $timeoutCallback();
    });
  }

}
