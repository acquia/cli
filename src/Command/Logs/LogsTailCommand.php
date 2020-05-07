<?php

namespace Acquia\Cli\Command\Logs;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LogsTailCommand.
 */
class LogsTailCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('logs:tail')->setDescription('Tail the logs from your environments');
    // @todo Add option to accept environment uuid.
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->output->writeln('<comment>This is a command stub. The command logic has not been written yet.');
    return 0;
  }

}
