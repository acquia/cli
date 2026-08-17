<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\SasApi\SasClientService;
use Acquia\Cli\SasApi\SourceConfig;
use Psr\Log\LoggerInterface;
use SelfUpdate\SelfUpdateManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Trigger a Source config import on a site via the Sites Aggregation Service.
 *
 * This is a thin trigger: it asks SAS to run `drush source:config:import` on
 * the site's environment. The command sends no payload — the config is read
 * from the site's deployed git repository on the Acquia side, not from the
 * local machine.
 */
#[RequireAuth]
#[AsCommand(name: 'source:config:push', description: 'Import deployed Source configuration on a site')]
final class ConfigPushCommand extends CommandBase
{
    public function __construct(
        LocalMachineHelper $localMachineHelper,
        CloudDataStore $datastoreCloud,
        AcquiaCliDatastore $datastoreAcli,
        ApiCredentialsInterface $cloudCredentials,
        TelemetryHelper $telemetryHelper,
        string $projectDir,
        ClientService $cloudApiClientService,
        SshHelper $sshHelper,
        string $sshDir,
        LoggerInterface $logger,
        SelfUpdateManager $selfUpdateManager,
        private readonly SasClientService $sasClient,
    ) {
        parent::__construct(
            $localMachineHelper,
            $datastoreCloud,
            $datastoreAcli,
            $cloudCredentials,
            $telemetryHelper,
            $projectDir,
            $cloudApiClientService,
            $sshHelper,
            $sshDir,
            $logger,
            $selfUpdateManager,
        );
    }

    protected function configure(): void
    {
        $this
            ->acceptEnvironmentId()
            ->acceptSiteInstanceId()
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation before pushing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setDirAndRequireProjectCwd($input);

        $siteInstance = $this->determineSiteInstance($input);
        if ($siteInstance === null) {
            throw new AcquiaCliException(
                'Could not determine a Source site instance. Run this command from a repository linked to an Acquia Cloud application, or pass --siteInstanceId.'
            );
        }

        $environment = $siteInstance->environment;

        if (!$input->getOption('force')) {
            $answer = $this->io->confirm(
                sprintf('Import deployed configuration on the %s environment?', $environment->name),
                false,
            );
            if (!$answer) {
                return Command::SUCCESS;
            }
        }

        $sourceConfig = new SourceConfig($this->sasClient->getClient());

        $response = $sourceConfig->push($environment->id);
        // @todo DXBE-20: Confirm the operation ID field name with the SAS team.
        $operationId = $response->id ?? null;
        if (!is_string($operationId)) {
            throw new AcquiaCliException('The SAS API response did not include an operation ID.');
        }

        $this->io->writeln(sprintf('Config import submitted (operation %s). Waiting for it to complete...', $operationId));

        return $this->waitForPush($sourceConfig, $operationId) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Poll the operation until it leaves the in-progress states.
     *
     * @todo DXBE-20: Confirm the status field name and its values with the
     *   SAS team. Assumes a `status` field mirroring the task gateway's
     *   phases (pending/running/succeeded/failed).
     */
    private function waitForPush(SourceConfig $sourceConfig, string $operationId): bool
    {
        $status = null;
        $checkStatus = static function () use ($sourceConfig, $operationId, &$status): bool {
            $response = $sourceConfig->getPushStatus($operationId);
            $status = $response->status ?? 'unknown';
            return !in_array($status, ['pending', 'running'], true);
        };
        $onDone = static function (): void {
        };

        LoopHelper::getLoopy($this->output, $this->io, 'Importing configuration...', $checkStatus, $onDone);

        if ($status === 'succeeded') {
            $this->io->success('Configuration imported successfully.');
            return true;
        }

        $this->io->error(sprintf('Config import ended with status: %s', $status));
        return false;
    }
}
