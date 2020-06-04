<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Helpers\DataStoreContract;
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
    $this->setName('telemetry')
      ->setDescription('Toggle anonymous sharing of usage and performance data');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $datastore = $this->getApplication()->getContainer()->get('acli_datastore');
    if ($datastore->get(DataStoreContract::SEND_TELEMETRY)) {
      $datastore->set(DataStoreContract::SEND_TELEMETRY, FALSE);
      $this->output->writeln('<info>Telemetry has been disabled.</info>');
    }
    else {
      $datastore->set(DataStoreContract::SEND_TELEMETRY, TRUE);
      $this->output->writeln('<info>Telemetry has been enabled.</info>');
    }

    return 0;
  }

}
