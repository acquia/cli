<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'self:status')]

final class SelfStatusCommand extends CommandBase
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setStyle('borderless');
        $table->setHeaders(['Property', 'Value']);
        $table->addRow(['Version', $this->getApplication()->getVersion()]);
        $table->addRow(['Cloud datastore', $this->datastoreCloud->filepath]);
        $table->addRow(['ACLI datastore', $this->datastoreAcli->filepath]);
        $table->addRow(['Telemetry enabled', var_export($this->telemetryHelper->telemetryEnabled(), true)]);
        $table->addRow(['User ID', $this->telemetryHelper->getUserId()]);
        foreach ($this->telemetryHelper->getTelemetryUserData() as $key => $value) {
            $table->addRow([$key, $value]);
        }
        $table->render();
        return Command::SUCCESS;
    }
}
