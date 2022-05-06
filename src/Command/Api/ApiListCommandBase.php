<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ApiListCommandBase class.
 */
class ApiListCommandBase extends CommandBase {

  /**
   * @var string
   */
  protected string $namespace;

  /**
   * @param string $namespace
   */
  public function setNamespace(string $namespace): void {
    $this->namespace = $namespace;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $commands = $this->getApplication()->all();
    foreach ($commands as $command) {
      if ($command->getName() !== $this->namespace
        && str_contains($command->getName(), $this->namespace . ':')
        // This is a lazy way to exclude api:base and acsf:base.
        && $command->getDescription()
        ) {
        $command->setHidden(FALSE);
      }
      else {
        $command->setHidden(TRUE);
      }
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
