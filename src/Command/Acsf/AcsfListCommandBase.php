<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AcsfListCommandBase extends CommandBase {

  protected string $namespace;

  public function setNamespace(string $namespace): void {
    $this->namespace = $namespace;
  }

  /**
   * Indicates whether the command requires the machine to be authenticated with the Cloud Platform.
   */
  protected function commandRequiresAuthentication(): bool {
    // Assume commands require authentication unless they opt out by overriding this method.
    return FALSE;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $commands = $this->getApplication()->all();
    foreach ($commands as $command) {
      if ($command->getName() !== $this->namespace
        // E.g., if the namespace is acsf:api, show all acsf:api:* commands.
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
      'namespace' => 'acsf',
    ];
    $listInput = new ArrayInput($arguments);

    return $command->run($listInput, $output);
  }

}
