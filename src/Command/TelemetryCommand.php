<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Helpers\DataStoreContract;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TelemetryCommand.
 */
class TelemetryCommand extends CommandBase {

  protected static $defaultName = 'telemetry:toggle';

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    return FALSE;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Toggle anonymous sharing of usage and performance data')
      ->setAliases(['telemetry']);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $datastore = $this->datastoreCloud;
    if ($datastore->get(DataStoreContract::SEND_TELEMETRY)) {
      $datastore->set(DataStoreContract::SEND_TELEMETRY, FALSE);
      $this->output->writeln('<info>Telemetry has been disabled.</info>');
    }
    else {
      $datastore->set(DataStoreContract::SEND_TELEMETRY, TRUE);
      $this->output->writeln('<info>Telemetry has been enabled.</info>');
    }
    $opposite_verb = $datastore->get(DataStoreContract::SEND_TELEMETRY) ? 'disable' : 'enable';
    $this->output->writeln("<info>Run this command again to $opposite_verb telemetry</info>");

    return 0;
  }

}
