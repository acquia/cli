<?php

namespace Acquia\Cli\Command\Acsf;

/**
 *
 */
class AcsfListCommand extends AcsfListCommandBase {

  protected static $defaultName = 'acsf:list';
  protected $namespace = 'acsf';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription("List all Acquia Cloud Site Factory commands")
      ->setAliases(['acsf']);
  }

}
