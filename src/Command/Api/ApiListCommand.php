<?php

namespace Acquia\Cli\Command\Api;

class ApiListCommand extends ApiListCommandBase {

  /**
   * @var string
   */
  protected static $defaultName = 'api:list';
  protected string $namespace = 'api';

  protected function configure(): void {
    $this->setDescription("List all API commands")
      ->setAliases(['api']);
  }

}
