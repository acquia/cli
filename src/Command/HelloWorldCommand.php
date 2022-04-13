<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class HelloWorldCommand.
 */
class HelloWorldCommand extends ApiCommandBase {

  protected static $defaultName = 'hello-world';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Test command used for asserting core functionality')
      ->setHidden(TRUE);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->io->success('Hello world!');

    return 0;
  }

}
