<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:link')]
class LinkCommand extends CommandBase {

  /**
   * @var string
   */
  // phpcs:ignore
  protected static $defaultDescription = 'Associate your project with a Cloud Platform application';

  protected function configure(): void {
    $this
      ->setAliases(['link']);
    $this->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->validateCwdIsValidDrupalProject();
    if ($cloudApplicationUuid = $this->getCloudUuidFromDatastore()) {
      $cloudApplication = $this->getCloudApplication($cloudApplicationUuid);
      $output->writeln('This repository is already linked to Cloud application <options=bold>' . $cloudApplication->name . '</>. Run <options=bold>acli unlink</> to unlink it.');
      return 1;
    }
    $this->determineCloudApplication(TRUE);

    return Command::SUCCESS;
  }

}
