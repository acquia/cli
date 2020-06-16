<?php

namespace Acquia\Cli\Output;

use Acquia\Cli\Output\Spinner\Spinner;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class Checklist {
  /**
   * @var array*/
  private $items = [];

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private $output;

  /**
   * Checklist constructor.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function __construct(OutputInterface $output) {
    $this->output = $output;
  }

  /**
   * @param $message
   */
  public function addItem($message): void {
    $item = ['message' => $message];

    if ($this->useSpinner()) {
      $spinner = new Spinner($this->output, 4);
      $spinner->setMessage($message . '...');
      $spinner->start();
      $item['spinner'] = $spinner;
    }

    $this->items[] = $item;
  }

  /**
   */
  public function completePreviousItem(): void {
    if ($this->useSpinner()) {
      $item = $this->getLastItem();
      /** @var \Acquia\Cli\Output\Spinner\Spinner $spinner */
      $spinner = $item['spinner'];
      $spinner->setMessage($item['message']);
      $spinner->advance();
      $spinner->finish();
    }
  }

  /**
   *
   */
  protected function getLastItem() {
    return end($this->items);
  }

  /**
   * @param $update_message
   */
  public function updateProgressBar($update_message): void {
    if ($this->useSpinner()) {
      $item = $this->getLastItem();
      /** @var \Acquia\Cli\Output\Spinner\Spinner $spinner */
      $spinner = $item['spinner'];
    }

    $message_lines = explode(PHP_EOL, $update_message);
    foreach ($message_lines as $line) {
      if ($this->useSpinner()) {
        $spinner->advance();
      }
    }
  }

  /**
   *
   */
  private function useSpinner(): bool {
    return $this->output instanceof ConsoleOutput;
  }

  /**
   * @return array
   */
  public function getItems(): array {
    return $this->items;
  }

}
