<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CodeStudioProjectConfigureCommand.
 */
class CodeStudioProjectConfigureCommand extends CommandBase {

  protected static $defaultName = 'codestudio:project:configure';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    return 0;
  }

}
