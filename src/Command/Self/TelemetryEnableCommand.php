<?php

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Helpers\DataStoreContract;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TelemetryEnableCommand extends CommandBase {

  protected static $defaultName = 'self:telemetry:enable';

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function configure(): void {
    $this->setDescription('Enable anonymous sharing of usage and performance data')
      ->setAliases(['telemetry:enable']);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $datastore = $this->datastoreCloud;
    $datastore->set(DataStoreContract::SEND_TELEMETRY, TRUE);
    $this->io->success('Telemetry has been enabled.');

    return Command::SUCCESS;
  }

}
