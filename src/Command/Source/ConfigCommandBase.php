<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\ApiCredentialsInterface;
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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for Source config commands.
 *
 * Both directions are thin triggers over the SAS API: they ask SAS to run a
 * `drush source:config:*` command on the environment. Push (import) reads
 * config from the site's git repository into the CMS; pull (export) does the
 * reverse and returns the exported config for the command to write to disk.
 * No push payload travels through these commands.
 */
abstract class ConfigCommandBase extends CommandBase
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
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation');
    }

    /**
     * Trigger the config operation on the environment and return the decoded response.
     */
    abstract protected function triggerOperation(SourceConfig $sourceConfig, string $environmentId): object;

    /**
     * A short verb phrase describing the operation, e.g. "Importing configuration".
     */
    abstract protected function operationLabel(): string;

    /**
     * Handle a successfully completed operation.
     *
     * The default does nothing (push). Pull overrides this to fetch the
     * exported payload and write it to disk.
     */
    protected function onSuccess(SourceConfig $sourceConfig, string $operationId): int
    {
        $this->io->success($this->operationLabel() . ' completed successfully.');

        return Command::SUCCESS;
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
                sprintf('%s on the %s environment?', $this->operationLabel(), $environment->name),
                false,
            );
            if (!$answer) {
                return Command::SUCCESS;
            }
        }

        $sourceConfig = new SourceConfig($this->sasClient->getClient());

        $response = $this->triggerOperation($sourceConfig, $environment->id);
        // @todo DXBE-20: Confirm the operation ID field name with the SAS team.
        $operationId = $response->id ?? null;
        if (!is_string($operationId)) {
            throw new AcquiaCliException('The SAS API response did not include an operation ID.');
        }

        $this->io->writeln(sprintf('%s submitted (operation %s). Waiting for it to complete...', $this->operationLabel(), $operationId));

        if (!$this->waitForOperation($sourceConfig, $operationId)) {
            return Command::FAILURE;
        }

        return $this->onSuccess($sourceConfig, $operationId);
    }

    /**
     * Poll the operation until it leaves the in-progress states.
     *
     * @todo DXBE-20: Confirm the status field name and its values with the
     *   SAS team. Assumes a `status` field mirroring the task gateway's
     *   phases (pending/running/succeeded/failed).
     */
    private function waitForOperation(SourceConfig $sourceConfig, string $operationId): bool
    {
        $status = null;
        $checkStatus = static function () use ($sourceConfig, $operationId, &$status): bool {
            $response = $sourceConfig->getStatus($operationId);
            $status = $response->status ?? 'unknown';
            return !in_array($status, ['pending', 'running'], true);
        };
        $onDone = static function (): void {
        };

        // @infection-ignore-all The spinner message is transient (overwritten
        // as the spinner advances) and never appears in the captured output,
        // so its concatenation cannot be asserted by a test.
        LoopHelper::getLoopy($this->output, $this->io, $this->operationLabel() . '...', $checkStatus, $onDone);

        if ($status !== 'succeeded') {
            $this->io->error(sprintf('%s ended with status: %s', $this->operationLabel(), $status));
        }

        return $status === 'succeeded';
    }
}
