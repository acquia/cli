<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Attribute\RequireAuth;
use Symfony\Component\Console\Attribute\AsCommand;

#[RequireAuth]
#[AsCommand(name: 'api:list', description: 'List all API commands', aliases: ['api'])]
final class ApiListCommand extends ApiListCommandBase
{
    protected string $namespace = 'api';
}
