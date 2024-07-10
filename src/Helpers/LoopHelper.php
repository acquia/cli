<?php

declare(strict_types=1);

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Output\Spinner\Spinner;
use React\EventLoop\Loop;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LoopHelper
{
    /**
     * @param callable $statusCallback A TRUE return value will cause the loop to exit and call $doneCallback.
     */
    public static function getLoopy(OutputInterface $output, SymfonyStyle $io, string $spinnerMessage, callable $statusCallback, callable $doneCallback): void
    {
        $timers = [];
        $spinner = new Spinner($output, 4);
        $spinner->setMessage($spinnerMessage);
        $spinner->start();

        $cancelTimers = static function () use (&$timers, $spinner): void {
            // @infection-ignore-all
            array_map('\React\EventLoop\Loop::cancelTimer', $timers);
            $timers = [];
            $spinner->finish();
        };
        $periodicCallback = static function () use ($statusCallback, $doneCallback, $cancelTimers): void {
            // @infection-ignore-all
            if ($statusCallback()) {
                $cancelTimers();
                $doneCallback();
            }
        };

        // Spinner timer.
        $timers[] = Loop::addPeriodicTimer(
            $spinner->interval(),
            static function () use ($spinner): void {
                $spinner->advance();
            }
        );

        // Primary timer checking for result status.
        $timers[] = Loop::addPeriodicTimer(5, $periodicCallback);
        // Initial timer to speed up tests.
        $timers[] = Loop::addTimer(0.1, $periodicCallback);

        // Watchdog timer.
        $timers[] = Loop::addTimer(45 * 60, static function () use ($io, $doneCallback, $cancelTimers): void {
            $cancelTimers();
            $io->error("Timed out after 45 minutes!");
            $doneCallback();
        });

        // Manually run the loop. React EventLoop advises against this and suggests
        // using autorun instead, but I'm not sure how to pass the correct exit code
        // to Symfony if this isn't blocking.
        Loop::run();
    }
}
