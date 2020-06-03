<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LinkCommand.
 */
class LinkCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('link')->setDescription('Associate your project with an Acquia Cloud application')
    ->addOption('cloud-app-uuid', 'uuid', InputOption::VALUE_REQUIRED, 'The UUID of the associated Acquia Cloud Application');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->validateCwdIsValidDrupalProject();
    if ($cloud_application_uuid = $this->getAppUuidFromLocalProjectInfo()) {
      $cloud_application = $this->getCloudApplication($cloud_application_uuid);
      $output->writeln('This repository is already linked to Cloud application <comment>' . $cloud_application->name . '</comment>. Run <comment>acli unlink</comment> to unlink it.');
      return 1;
    }
    $cloud_application_uuid = $this->determineCloudApplication(TRUE);

    return 0;
  }

}
