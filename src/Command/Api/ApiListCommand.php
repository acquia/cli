<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Api;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'api:list')]
class ApiListCommand extends ApiListCommandBase {

  protected string $namespace = 'api';

  protected function configure(): void {
    $this->setDescription("List all API commands")
      ->setAliases(['api']);
  }

}
