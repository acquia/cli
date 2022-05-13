<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Spinner\Spinner;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

class LoopHelper {

  /**
   * @param \React\EventLoop\LoopInterface $loop
   *
   * @param string $message
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return \Acquia\Cli\Output\Spinner\Spinner
   */
  public static function addSpinnerToLoop(
    LoopInterface $loop,
    $message,
    $output
  ): Spinner {
    $spinner = new Spinner($output, 4);
    $spinner->setMessage($message);
    $spinner->start();
    $loop->addPeriodicTimer($spinner->interval(),
      static function () use ($spinner) {
        $spinner->advance();
      });

    return $spinner;
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
    Spinner $spinner
  ): TimerInterface {
    return $loop->addTimer($minutes * 60, function () use ($loop, $minutes, $spinner) {
      self::finishSpinner($spinner);
      $loop->stop();
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
