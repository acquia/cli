<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LinkCommand extends CommandBase {

  protected static $defaultName = 'app:link';

  protected function configure(): void {
    $this->setDescription('Associate your project with a Cloud Platform application')
      ->setAliases(['link']);
    $this->acceptApplicationUuid();
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->validateCwdIsValidDrupalProject();
    if ($cloudApplicationUuid = $this->getCloudUuidFromDatastore()) {
      $cloudApplication = $this->getCloudApplication($cloudApplicationUuid);
      $output->writeln('This repository is already linked to Cloud application <options=bold>' . $cloudApplication->name . '</>. Run <options=bold>acli unlink</> to unlink it.');
      return 1;
    }
    $this->determineCloudApplication(TRUE);

    return 0;
  }

}
