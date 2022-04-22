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

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription("List all Acquia Cloud Site Factory commands")
      ->setAliases(['acsf']);
  }

  public function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->namespace = 'acsf';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $command = $this->getApplication()->find('list');
    $arguments = [
      'command' => 'list',
      'namespace' => $this->namespace,
    ];
    $list_input = new ArrayInput($arguments);

    return $command->run($list_input, $output);
  }

}
