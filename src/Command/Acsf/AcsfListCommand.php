<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'acsf:list', 'List all Acquia Cloud Site Factory commands', ['acsf'])]
class AcsfListCommand extends AcsfListCommandBase {

  protected string $namespace = 'acsf';

  protected function configure(): void
  {
  }

}
