<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'self:info', description: 'Print information about the running version of Acquia CLI')]

final class SelfInfoCommand extends CommandBase
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = $this->createTable($output, 'Acquia CLI information', ['Property', 'Value']);
        $application = $this->getApplication();
        $version = ($application && method_exists($application, 'getVersion')) ? $application->getVersion() : null;
        $table->addRow(['Version', $version ?? 'unknown']);
        $buildDate = ($application && method_exists($application, 'getBuildDate')) ? $application->getBuildDate() : null;
        $table->addRow(['Build date', $buildDate ?? 'unknown']);
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
