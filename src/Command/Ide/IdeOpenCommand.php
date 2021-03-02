<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Ides;
use http\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeOpenCommand.
 */
class IdeOpenCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:open';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Open a Cloud IDE in your browser')
      ->setHidden(AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
    $this->acceptApplicationUuid();
    // @todo Add option to accept an ide UUID.
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_application_uuid = $this->determineCloudApplication();
    if (!$cloud_application_uuid && !$input->isInteractive()) {
      throw new \Symfony\Component\Console\Exception\RuntimeException('Not enough arguments (missing: "applicationUuid").');
    }
    $ides_resource = new Ides($acquia_cloud_client);
    $ide = $this->promptIdeChoice("Please select the IDE you'd like to open:", $ides_resource, $cloud_application_uuid);

    $this->output->writeln('');
    $this->output->writeln("<comment>Your IDE URL:</comment> <href={$ide->links->ide->href}>{$ide->links->ide->href}</>");
    $this->output->writeln("<comment>Your Drupal Site URL:</comment> <href={$ide->links->web->href}>{$ide->links->web->href}</>");
    $this->output->writeln('Opening your IDE in browser...');

    $this->localMachineHelper->startBrowser($ide->links->ide->href);

    return 0;
  }

}
