<?php

namespace Acquia\Cli;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Application extends \Symfony\Component\Console\Application {


  protected $helpMessages = [];

  /**
   * @return mixed
   */
  public function getHelpMessages(): array {
    return $this->helpMessages;
  }

  /**
   * @param mixed $helpMessages
   */
  public function setHelpMessages($helpMessages): void {
    $this->helpMessages = $helpMessages;
  }

  public function renderThrowable(
    \Throwable $e,
    OutputInterface $output
  ): void {
    parent::renderThrowable($e, $output);

    if ($this->getHelpMessages()) {
      $io = new SymfonyStyle(new ArrayInput([]), $output);
      $output_style = new OutputFormatterStyle(NULL, 'blue');
      $output->getFormatter()->setStyle('help', $output_style);
      $io->block($this->getHelpMessages(), 'help', 'help', ' ', TRUE, FALSE);
    }
  }

}
