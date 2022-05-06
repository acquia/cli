<?php

namespace Acquia\Cli\Command\Api;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
