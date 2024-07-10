<?php

declare(strict_types=1);

namespace Acquia\Cli\Output\Spinner;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class Spinner
{
    private const CHARS = ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇'];
    private const COLORS = [
    196,
    196,
    202,
    202,
    208,
    208,
    214,
    214,
    220,
    220,
    226,
    226,
    190,
    190,
    154,
    154,
    118,
    118,
    82,
    82,
    46,
    46,
    47,
    47,
    48,
    48,
    49,
    49,
    50,
    50,
    51,
    51,
    45,
    45,
    39,
    39,
    33,
    33,
    27,
    27,
    56,
    56,
    57,
    57,
    93,
    93,
    129,
    129,
    165,
    165,
    201,
    201,
    200,
    200,
    199,
    199,
    198,
    198,
    197,
    197,
    ];

    private int $currentCharIdx = 0;

    private int $currentColorIdx = 0;

    private ?int $colorCount;

    private ProgressBar $progressBar;

    private ConsoleSectionOutput $section;

    public function __construct(private OutputInterface $output, private int $indentLength = 0)
    {
        $indentString = str_repeat(' ', $indentLength);

        if (!$this->spinnerIsSupported()) {
            return;
        }
        $this->section = $output->section();
        $this->colorCount = count(self::COLORS);

        // Create progress bar.
        $this->progressBar = new ProgressBar($this->section);
        $this->progressBar->setBarCharacter('<info>✔</info>');
        $this->progressBar->setProgressCharacter('⌛');
        $this->progressBar->setEmptyBarCharacter('⌛');
        $this->progressBar->setFormat($indentString . "%bar% %message%\n%detail%");
        $this->progressBar->setBarWidth(1);
        $this->progressBar->setMessage(' ', 'detail');
        $this->progressBar->setOverwrite($output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE);
    }

    public function start(): void
    {
        if (!$this->spinnerIsSupported()) {
            return;
        }
        $this->progressBar->start();
    }

    public function advance(): void
    {
        if (!$this->spinnerIsSupported() || $this->progressBar->getProgressPercent() === 1.0) {
            return;
        }

        ++$this->currentCharIdx;
        ++$this->currentColorIdx;
        $char = $this->getSpinnerCharacter();
        $this->progressBar->setProgressCharacter($char);
        $this->progressBar->advance();
    }

    private function getSpinnerCharacter(): string
    {
        if ($this->currentColorIdx === $this->colorCount) {
            $this->currentColorIdx = 0;
        }
        $char = self::CHARS[$this->currentCharIdx % 8];
        $color = self::COLORS[$this->currentColorIdx];
        return "\033[38;5;{$color}m$char\033[0m";
    }

    public function setMessage(string $message, string $name = 'message'): void
    {
        if (!$this->spinnerIsSupported()) {
            return;
        }
        if ($name === 'detail') {
            $terminalWidth = (new Terminal())->getWidth();
            $messageLength = Helper::length($message) + ($this->indentLength * 2);
            if ($messageLength > $terminalWidth) {
                $suffix = '...';
                $newMessageLen = ($terminalWidth - ($this->indentLength * 2) - strlen($suffix));
                $message = Helper::substr($message, 0, $newMessageLen);
                $message .= $suffix;
            }
        }
        $this->progressBar->setMessage($message, $name);
    }

    public function finish(): void
    {
        if (!$this->spinnerIsSupported()) {
            return;
        }
        $this->progressBar->finish();
        // Clear the %detail% line.
        $this->section->clear(1);
    }

    public function fail(): void
    {
        if (!$this->spinnerIsSupported()) {
            return;
        }
        $this->progressBar->finish();
        // Clear the %detail% line.
        $this->section->clear(1);
    }

    /**
     * Returns spinner refresh interval.
     */
    public function interval(): float
    {
        return 0.1;
    }

    private function spinnerIsSupported(): bool
    {
        return $this->output instanceof ConsoleOutput
        && (getenv('CI') !== 'true' || getenv('PHPUNIT_RUNNING'));
    }

    public function getProgressBar(): ProgressBar
    {
        return $this->progressBar;
    }
}
