<?php

namespace Acquia\Ads\Command\Ide;

use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeOpenCommand.
 */
class IdeOpenCommand extends IdeCommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ide:open')->setDescription('The application associated with the IDE');
    // @todo Add option to specify application uuid.
    // @todo Add option to accept an ide UUID.
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->getAcquiaCloudClient();
    $ides_resource = new Ides($acquia_cloud_client);
    $ide = $this->promptIdeChoice("Please select the IDE you'd like to open:", $ides_resource);

    $this->output->writeln('');
    $this->output->writeln('<comment>Your IDE URL:</comment> ' . $ide->links->ide->href);
    $this->output->writeln('<comment>Your Drupal Site URL:</comment> ' . $ide->links->web->href);
    $this->output->writeln('Opening your IDE in browser...');

    $this->getApplication()->getLocalMachineHelper()->startBrowser($ide->links->ide->href);

    return 0;
  }

}
