<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'acsf:list')]
class AcsfListCommand extends AcsfListCommandBase {

  /**
   * @var string
   */
  // phpcs:ignore
  protected static $defaultDescription = "List all Acquia Cloud Site Factory commands";
  protected string $namespace = 'acsf';

  protected function configure(): void {
    $this
      ->setAliases(['acsf']);
  }

}
