<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Logs;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class OpenCommand.
 */
class OpenCommand extends CommandBase {

  protected static $defaultName = 'open:application';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Opens your browser to view a given Cloud application')
      ->acceptApplicationUuid()
      ->setHidden(!LocalMachineHelper::isBrowserAvailable())
      ->setAliases(['open', 'open:app', 'o']);
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
    $application_uuid = $this->determineCloudApplication();
    $this->localMachineHelper->startBrowser('https://cloud.acquia.com/a/applications/' . $application_uuid);

    return 0;
  }

}