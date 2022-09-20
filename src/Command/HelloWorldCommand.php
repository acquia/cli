<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class HelloWorldCommand.
 */
class HelloWorldCommand extends CommandBase {

  protected static $defaultName = 'hello-world';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Test command used for asserting core functionality')
      ->setHidden();
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->io->success('Hello world!');

    return 0;
  }

}
