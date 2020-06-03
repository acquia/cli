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
   * The default command name.
   *
   * @var string
   */
  protected static $defaultName = 'link';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Associate your project with an Acquia Cloud application')
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
    // @todo Indicate if the current local repo is already associated with a cloud
    // application. Confirm to overwrite.
    $cloud_application_uuid = $this->determineCloudApplication(TRUE);

    return 0;
  }

}
