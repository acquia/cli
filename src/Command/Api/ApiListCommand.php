<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Api;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'api:list', description: 'List all API commands', aliases: ['api'])]
class ApiListCommand extends ApiListCommandBase {

  protected string $namespace = 'api';

  protected function configure(): void {
  }

}
