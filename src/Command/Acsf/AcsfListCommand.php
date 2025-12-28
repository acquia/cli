<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\Attribute\RequireAuth;
use Symfony\Component\Console\Attribute\AsCommand;

#[RequireAuth]
#[AsCommand(name: 'acsf:list', description: 'List all Acquia Cloud Site Factory commands (Added in 1.30.1)', aliases: ['acsf'])]
final class AcsfListCommand extends AcsfListCommandBase
{
    protected string $namespace = 'acsf';
}
