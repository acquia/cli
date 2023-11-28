<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:open', 'Opens your browser to view a given Cloud application', ['open', 'o'])]
class AppOpenCommand extends CommandBase {

  protected function configure(): void {
    $this
      ->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!$this->localMachineHelper->isBrowserAvailable()) {
      throw new AcquiaCliException('No browser is available on this machine');
    }
    $applicationUuid = $this->determineCloudApplication();
    $this->localMachineHelper->startBrowser('https://cloud.acquia.com/a/applications/' . $applicationUuid);

    return Command::SUCCESS;
  }

}
