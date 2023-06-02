<?php

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Helpers\DataStoreContract;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TelemetryDisableCommand extends CommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'self:telemetry:disable';

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function configure(): void {
    $this->setDescription('Disable anonymous sharing of usage and performance data')
      ->setAliases(['telemetry:disable']);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $datastore = $this->datastoreCloud;
    $datastore->set(DataStoreContract::SEND_TELEMETRY, FALSE);
    $this->io->success('Telemetry has been disabled.');

    return Command::SUCCESS;
  }

}
