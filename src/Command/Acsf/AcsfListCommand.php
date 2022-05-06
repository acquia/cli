<?php

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
