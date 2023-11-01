<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'acsf:list')]
class AcsfListCommand extends AcsfListCommandBase {

  protected string $namespace = 'acsf';

  protected function configure(): void {
    $this->setDescription("List all Acquia Cloud Site Factory commands")
      ->setAliases(['acsf']);
  }

}
