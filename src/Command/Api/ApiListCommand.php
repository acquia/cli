<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class ApiListCommand extends CommandBase {

  protected static $defaultName = 'api:list';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription("<fg=cyan>There are more hidden API commands! Run api:list to see them all.</>")
      ->setAliases(['api']);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Un-hide api:* commands.
    $api_commands = $this->getApplication()->all('api');
    foreach ($api_commands as $api_command) {
      $api_command->setHidden(FALSE);
    }

    $command = $this->getApplication()->find('list');
    $arguments = [
      'command' => 'list',
      'namespace' => 'api',
    ];
    $list_input = new ArrayInput($arguments);

    return $command->run($list_input, $output);
  }

}
