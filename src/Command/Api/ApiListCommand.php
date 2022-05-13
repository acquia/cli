<?php

namespace Acquia\Cli\Command\Api;

/**
 *
 */
class ApiListCommand extends ApiListCommandBase {

  protected static $defaultName = 'api:list';
  protected string $namespace = 'api';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription("List all API commands")
      ->setAliases(['api']);
  }

}
