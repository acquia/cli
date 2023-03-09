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

  protected string $namespace;

  /**
   */
  public function setNamespace(string $namespace): void {
    $this->namespace = $namespace;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Symfony\Component\Console\Exception\ExceptionInterface
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
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
