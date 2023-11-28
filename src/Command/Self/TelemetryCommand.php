<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Helpers\DataStoreContract;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'self:telemetry:toggle', description: 'Toggle anonymous sharing of usage and performance data', aliases: ['telemetry'])]
class TelemetryCommand extends CommandBase {

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function configure(): void {
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $datastore = $this->datastoreCloud;
    if ($datastore->get(DataStoreContract::SEND_TELEMETRY)) {
      $datastore->set(DataStoreContract::SEND_TELEMETRY, FALSE);
      $this->io->success('Telemetry has been disabled.');
    }
    else {
      $datastore->set(DataStoreContract::SEND_TELEMETRY, TRUE);
      $this->io->success('Telemetry has been enabled.');
    }
    $oppositeVerb = $datastore->get(DataStoreContract::SEND_TELEMETRY) ? 'disable' : 'enable';
    $this->io->writeln("Run this command again to $oppositeVerb telemetry");

    return Command::SUCCESS;
  }

}
