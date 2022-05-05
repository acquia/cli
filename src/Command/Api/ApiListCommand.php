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

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription("List all API commands")
      ->setAliases(['api']);
  }

  public function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->namespace = 'api';
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
