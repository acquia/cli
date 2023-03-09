<?php

namespace Acquia\Cli;

use Composer\Semver\VersionParser;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use UnexpectedValueException;

class Application extends \Symfony\Component\Console\Application {

  /**
   * @var array
   */
  protected array $helpMessages = [];

  /**
   * @return mixed
   */
  private function getHelpMessages(): array {
    return $this->helpMessages;
  }

  public function setHelpMessages(mixed $helpMessages): void {
    $this->helpMessages = $helpMessages;
  }

  /**
   * Return a Composer-compatible version string.
   *
   * Prevent conflicts with consolidation/self-update, which expects a Composer
   * normalized version.
   *
   * @see https://github.com/consolidation/self-update/pull/21
   *
   */
  public function getVersion(): string {
    $version = parent::getVersion();
    try {
      $version = (new VersionParser())->normalize($version);
    }
    catch (UnexpectedValueException) {
      // Use version as-is.
    }
    return $version;
  }

  public function renderThrowable(
    Throwable $e,
    OutputInterface $output
  ): void {
    parent::renderThrowable($e, $output);

    if ($this->getHelpMessages()) {
      $io = new SymfonyStyle(new ArrayInput([]), $output);
      $output_style = new OutputFormatterStyle('white', 'blue');
      $output->getFormatter()->setStyle('help', $output_style);
      $io->block($this->getHelpMessages(), 'help', 'help', ' ', TRUE, FALSE);
    }
  }

}
