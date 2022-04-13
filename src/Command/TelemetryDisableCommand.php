<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Helpers\DataStoreContract;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TelemetryDisableCommand.
 */
class TelemetryDisableCommand extends CommandBase {

  protected static $defaultName = 'telemetry:disable';

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
    $this->setDescription('Disable anonymous sharing of usage and performance data');
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
    $datastore->set(DataStoreContract::SEND_TELEMETRY, FALSE);
    $this->io->success('Telemetry has been disabled.');

    return 0;
  }

}
