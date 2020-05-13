<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TelemetryCommand.
 */
class TelemetryCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('telemetry')->setDescription('Toggle anonymous sharing of usage and performance data');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $datastore = $this->getDatastore();
    if ($datastore->get('send_telemetry')) {
      $datastore->set('send_telemetry', FALSE);
      $this->output->writeln('Telemetry has been disabled.');
    }
    else {
      $datastore->set('send_telemetry', TRUE);
      $this->output->writeln('Telemetry has been enabled.');
    }

    return 0;
  }

}
