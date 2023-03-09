<?php

namespace Acquia\Cli\Command\Acsf;

class AcsfListCommand extends AcsfListCommandBase {

  protected static $defaultName = 'acsf:list';
  protected string $namespace = 'acsf';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription("List all Acquia Cloud Site Factory commands")
      ->setAliases(['acsf']);
  }

}
