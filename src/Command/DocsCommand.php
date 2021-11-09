<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DocsCommand.
 */
class DocsCommand extends CommandBase {

  protected static $defaultName = 'docs';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Open Acquia CLI documentation in a web browser');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->localMachineHelper->startBrowser('https://docs.acquia.com/acquia-cli/');

    return 0;
  }

}
