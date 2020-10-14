<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LinkCommand.
 */
class LinkCommand extends CommandBase {

  protected static $defaultName = 'link';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Associate your project with a Cloud Platform application');
    $this->acceptApplicationUuid();
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->validateCwdIsValidDrupalProject();
    if ($cloud_application_uuid = $this->getAppUuidFromLocalProjectInfo()) {
      $cloud_application = $this->getCloudApplication($cloud_application_uuid);
      $output->writeln('This repository is already linked to Cloud application <options=bold>' . $cloud_application->name . '</>. Run <options=bold>acli unlink</> to unlink it.');
      return 1;
    }
    $cloud_application_uuid = $this->determineCloudApplication(TRUE);

    return 0;
  }

}
