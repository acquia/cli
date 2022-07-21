<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Spinner\Spinner;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

class LoopHelper {

  public static function getLoopy($message, $output, $checkStatus, $message2, $logger) {
    $loop = Loop::get();
    $timers = [];
    [$spinner, $spinnerTimer] = LoopHelper::addSpinnerToLoop($loop, $message, $output);
    $timers[] = $spinnerTimer;
    $checkIdeStatus = function () use ($loop, $spinner, &$timers, $checkStatus, $message2, $output, $logger) {
      try {
        if ($checkStatus) {
          LoopHelper::finishSpinner($spinner);
          foreach ($timers as $timer) {
            $loop->cancelTimer($timer);
          }
          $timers = [];
          $output->writeln('');
          $output->writeln('<info>' . $message2 . '</info>');
        }
      }
      catch (\Exception $e) {
        $logger->debug($e->getMessage());
      }
    };
    $timers[] = $loop->addPeriodicTimer(5, $checkIdeStatus);
    $timers[] = LoopHelper::addTimeoutToLoop($loop, 45, $spinner, $timers);
    return $loop;
  }

  /**
   * @param \React\EventLoop\LoopInterface $loop
   *
   * @param string $message
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return array
   */
  public static function addSpinnerToLoop(
    LoopInterface $loop,
    $message,
    $output
  ): array {
    $spinner = new Spinner($output, 4);
    $spinner->setMessage($message);
    $spinner->start();
    $timer = $loop->addPeriodicTimer($spinner->interval(),
      static function () use ($spinner) {
        $spinner->advance();
      });

    return [$spinner, $timer];
  }

  /**
   * @param \React\EventLoop\LoopInterface $loop
   * @param float $minutes
   * @param \Acquia\Cli\Output\Spinner\Spinner $spinner
   *
   * @return \React\EventLoop\TimerInterface
   */
  public static function addTimeoutToLoop(
    LoopInterface $loop,
    float $minutes,
    Spinner $spinner,
    array $timers
  ): TimerInterface {
    return $loop->addTimer($minutes * 60, function () use ($loop, $minutes, $spinner, &$timers) {
      self::finishSpinner($spinner);
      foreach ($timers as $timer) {
        $loop->cancelTimer($timer);
      }
      $timers = [];
      throw new AcquiaCliException("Timed out after $minutes minutes!");
    });
  }

  /**
   * @param \Acquia\Cli\Output\Spinner\Spinner $spinner
   */
  public static function finishSpinner(Spinner $spinner): void {
    $spinner->finish();
  }

}
