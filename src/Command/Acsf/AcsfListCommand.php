<?php

namespace Acquia\Cli\Command\Acsf;

class AcsfListCommand extends AcsfListCommandBase {

  protected static $defaultName = 'acsf:list';
  protected string $namespace = 'acsf';

  protected function configure(): void {
    $this->setDescription("List all Acquia Cloud Site Factory commands")
      ->setAliases(['acsf']);
  }

}
