<?php

declare(strict_types = 1);

namespace Acquia\Cli;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class Application extends \Symfony\Component\Console\Application {

  /**
   * @var string[]
   */
  protected array $helpMessages = [];

  /**
   * @return string[]
   */
  private function getHelpMessages(): array {
    return $this->helpMessages;
  }

  public function setHelpMessages(mixed $helpMessages): void {
    $this->helpMessages = $helpMessages;
  }

  public function renderThrowable(
    Throwable $e,
    OutputInterface $output
  ): void {
    parent::renderThrowable($e, $output);

    if ($this->getHelpMessages()) {
      $io = new SymfonyStyle(new ArrayInput([]), $output);
      $outputStyle = new OutputFormatterStyle('white', 'blue');
      $output->getFormatter()->setStyle('help', $outputStyle);
      $io->block($this->getHelpMessages(), 'help', 'help', ' ', TRUE, FALSE);
    }
  }

}
