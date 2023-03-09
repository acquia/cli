<?php

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Helpers\DataStoreContract;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TelemetryDisableCommand.
 */
class TelemetryDisableCommand extends CommandBase {

  protected static $defaultName = 'self:telemetry:disable';

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Disable anonymous sharing of usage and performance data')
      ->setAliases(['telemetry:disable']);
  }

  /**
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $datastore = $this->datastoreCloud;
    $datastore->set(DataStoreContract::SEND_TELEMETRY, FALSE);
    $this->io->success('Telemetry has been disabled.');

    return 0;
  }

}
