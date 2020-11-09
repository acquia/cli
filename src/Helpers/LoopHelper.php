<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Spinner\Spinner;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
   * @param $minutes
   * @param \Acquia\Cli\Output\Spinner\Spinner $spinner
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public static function addTimeoutToLoop(
    LoopInterface $loop,
    $minutes,
    Spinner $spinner,
    OutputInterface $output
  ): void {
    $loop->addTimer($minutes * 60, function () use ($loop, $minutes, $spinner, $output) {
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
