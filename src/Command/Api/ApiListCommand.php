<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Api;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'api:list')]
class ApiListCommand extends ApiListCommandBase {

  /**
   * @var string
   */
  // phpcs:ignore
  protected static $defaultDescription = "List all API commands";
  protected string $namespace = 'api';

  protected function configure(): void {
    $this
      ->setAliases(['api']);
  }

}
