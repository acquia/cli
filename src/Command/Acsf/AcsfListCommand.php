<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'acsf:list', description: 'List all Acquia Cloud Site Factory commands', aliases: ['acsf'])]
class AcsfListCommand extends AcsfListCommandBase {

  protected string $namespace = 'acsf';

}
