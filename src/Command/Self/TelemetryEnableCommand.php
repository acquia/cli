<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Helpers\DataStoreContract;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'self:telemetry:enable', description: 'Enable anonymous sharing of usage and performance data', aliases: ['telemetry:enable'])]
final class TelemetryEnableCommand extends CommandBase
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $datastore = $this->datastoreCloud;
        $datastore->set(DataStoreContract::SEND_TELEMETRY, true);
        $this->io->success('Telemetry has been enabled.');

        return Command::SUCCESS;
    }
}
