<?php

namespace Acquia\Cli\Output\Spinner;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 *
 */
class Spinner {
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

  private int $colorLevel;

  private ConsoleSectionOutput $section;

  private OutputInterface $output;

  private int $indentLength;

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param int $indent
   * @param int $colorLevel
   */
  public function __construct(OutputInterface $output, int $indent = 0, int $colorLevel = Color::COLOR_256) {
    $this->output = $output;
    $this->indentLength = $indent;
    $indentString = str_repeat(' ', $indent);

    if (!$this->spinnerIsSupported()) {
      return;
    }
    $this->section = $output->section();
    $this->colorLevel = $colorLevel;
    $this->colorCount = count(self::COLORS);

    // Create progress bar.
    $this->progressBar = new ProgressBar($this->section);
    $this->progressBar->setBarCharacter('<info>✔</info>');
    $this->progressBar->setProgressCharacter('⌛');
    $this->progressBar->setEmptyBarCharacter('⌛');
    $this->progressBar->setFormat($indentString . "%bar% %message%\n%detail%");
    $this->progressBar->setBarWidth(1);
    $this->progressBar->setMessage('', 'detail');
    $this->progressBar->setOverwrite($output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE);
  }

  /**
   *
   */
  public function start(): void {
    if (!$this->spinnerIsSupported()) {
      return;
    }
    $this->progressBar->start();
  }

  /**
   *
   */
  public function advance(): void {
    if (!$this->spinnerIsSupported() || $this->progressBar->getProgressPercent() === 1.0) {
      return;
    }

    ++$this->currentCharIdx;
    ++$this->currentColorIdx;
    $char = $this->getSpinnerCharacter();
    $this->progressBar->setProgressCharacter($char);
    $this->progressBar->advance();
  }

  /**
   *
   */
  private function getSpinnerCharacter(): ?string {
    if ($this->currentColorIdx === $this->colorCount) {
      $this->currentColorIdx = 0;
    }
    $char = self::CHARS[$this->currentCharIdx % 8];
    $color = self::COLORS[$this->currentColorIdx];

    if (Color::COLOR_256 === $this->colorLevel) {
      return "\033[38;5;{$color}m{$char}\033[0m";
    }
    if (Color::COLOR_16 === $this->colorLevel) {
      return "\033[96m{$char}\033[0m";
    }

    return NULL;
  }

  /**
   * @param string $message
   * @param string $name
   */
  public function setMessage(string $message, string $name = 'message'): void {
    if (!$this->spinnerIsSupported()) {
      return;
    }
    if ($name === 'detail') {
      $terminal_width = (new Terminal())->getWidth();
      $message_length = Helper::length($message) + ($this->indentLength * 2);
      if ($message_length > $terminal_width) {
        $suffix = '...';
        $new_message_len = ($terminal_width - ($this->indentLength * 2) - strlen($suffix));
        $message = Helper::substr($message, 0, $new_message_len);
        $message .= $suffix;
      }
    }
    $this->progressBar->setMessage($message, $name);
  }

  /**
   *
   */
  public function finish(): void {
    if (!$this->spinnerIsSupported()) {
      return;
    }
    $this->progressBar->finish();
    // Clear the %detail% line.
    $this->section->clear(1);
  }

  /**
   *
   */
  public function fail(): void {
    if (!$this->spinnerIsSupported()) {
      return;
    }
    $this->progressBar->finish();
    // Clear the %detail% line.
    $this->section->clear(1);
  }

  /**
   * Returns spinner refresh interval.
   *
   * @return float
   */
  public function interval(): float {
    return 0.1;
  }

  /**
   *
   */
  private function spinnerIsSupported(): bool {
    return $this->output instanceof ConsoleOutput
      && (getenv('CI') !== 'true' || getenv('PHPUNIT_RUNNING'));
  }

  /**
   * @return \Symfony\Component\Console\Helper\ProgressBar
   */
  public function getProgressBar(): ProgressBar {
    return $this->progressBar;
  }

}
