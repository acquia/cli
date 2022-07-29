<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Output\Spinner\Spinner;
use React\EventLoop\Loop;

class LoopHelper {

  /**
   * @param $output
   * @param $io
   * @param $logger
   * @param $spinnerMessage
   * @param $statusCallback
   *   A TRUE return value will cause the loop to exit and call $doneCallback.
   * @param $doneCallback
   *
   * @return void
   */
  public static function getLoopy($output, $io, $logger, $spinnerMessage, $statusCallback, $doneCallback): void {
    $timers = [];
    $spinner = new Spinner($output, 4);
    $spinner->setMessage($spinnerMessage);
    $spinner->start();

    $cancelTimers = static function () use (&$timers, $spinner) {
      array_map('\React\EventLoop\Loop::cancelTimer', $timers);
      $timers = [];
      $spinner->finish();
    };
    $periodicCallback = static function () use ($logger, $statusCallback, $doneCallback, $cancelTimers) {
      try {
        if ($statusCallback()) {
          $cancelTimers();
          $doneCallback();
        }
      }
      catch (\Exception $e) {
        $logger->debug($e->getMessage());
      }
    };

    // Spinner timer.
    $timers[] = Loop::addPeriodicTimer($spinner->interval(),
      static function () use ($spinner) {
        $spinner->advance();
      });

    // Primary timer checking for result status.
    $timers[] = Loop::addPeriodicTimer(5, $periodicCallback);
    // Initial timer to speed up tests.
    $timers[] = Loop::addTimer(0.1, $periodicCallback);

    // Watchdog timer.
    $timers[] = Loop::addTimer(45 * 60, static function () use ($io, $doneCallback, $cancelTimers) {
      $cancelTimers();
      $io->error("Timed out after 45 minutes!");
      $doneCallback();
    });

    // Manually run the loop. React eventloop advises against this and suggests
    // using autorun instead, but I'm not sure how to pass the correct exit code
    // to Symfony if this isn't blocking.
    Loop::run();
  }

}
