<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Remote;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'remote:aliases:list', description: 'List all aliases for the Cloud Platform environments (Added in 1.0.0)', aliases: [
    'aliases',
    'sa',
])]
final class AliasListCommand extends CommandBase
{
    protected function configure(): void
    {
        $this->acceptApplicationUuid();
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $applicationsResource = new Applications($acquiaCloudClient);
        $cloudApplicationUuid = $this->determineCloudApplication();
        $customerApplication = $applicationsResource->get($cloudApplicationUuid);
        $environmentsResource = new Environments($acquiaCloudClient);

        $table = new Table($this->output);
        $table->setHeaderTitle('Environments for ' . $customerApplication->name);
        $table->setHeaders([
            'Alias',
            'UUID',
            'SSH URL',
        ]);

        $siteId = $customerApplication->hosting->id;
        $parts = explode(':', $siteId);
        $sitePrefix = $parts[1];
        $environments = $environmentsResource->getAll($customerApplication->uuid);
        /** @var \AcquiaCloudApi\Response\EnvironmentResponse $environment */
        foreach ($environments as $environment) {
            $alias = $sitePrefix . '.' . $environment->name;
            $table->addRow([
                $alias,
                $environment->uuid,
                $environment->sshUrl,
            ]);
        }

        $table->render();

        $output->writeln('<info>Run <options=bold>acli api:environments:find <alias></> to get more information about a specific environment.</info>');

        return Command::SUCCESS;
    }
}
