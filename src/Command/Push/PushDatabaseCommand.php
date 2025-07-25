<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Attribute\RequireLocalDb;
use Acquia\Cli\Attribute\RequireRemoteDb;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Response\SiteInstanceDatabaseResponse;
use AcquiaCloudApi\Response\SiteInstanceResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[RequireLocalDb]
#[RequireRemoteDb]
#[AsCommand(name: 'push:database', description: 'Push a database from your local environment to a Cloud Platform environment', aliases: ['push:db'])]
final class PushDatabaseCommand extends PushCommandBase
{
    protected function configure(): void
    {
        $this
            ->acceptSiteInstanceId();
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $siteInstance = $this->determineSiteInstance($input, $output);
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $database = $this->determineCloudDatabases($acquiaCloudClient, $siteInstance, $input->getArgument('site'));
        if ($database->databaseUser === null) {
            throw new AcquiaCliException('Database connection details missing');
        }
        $answer = $this->io->confirm("Overwrite the <bg=cyan;options=bold>$database->databaseName</> database on <bg=cyan;options=bold>" . $siteInstance->environment->name . "</> with a copy of the database from the current machine?");
        if (!$answer) {
            return Command::SUCCESS;
        }

        $this->checklist = new Checklist($output);
        $outputCallback = $this->getOutputCallback($output, $this->checklist);

        $this->checklist->addItem('Creating local database dump');
        $localDumpFilepath = $this->createMySqlDumpOnLocal($this->getLocalDbHost(), $this->getLocalDbUser(), $this->getLocalDbName(), $this->getLocalDbPassword(), $outputCallback);
        $this->checklist->completePreviousItem();

        $this->checklist->addItem('Uploading database dump to remote machine');
        $remoteDumpFilepath = $this->uploadDatabaseDump($siteInstance, $localDumpFilepath, $outputCallback);
        $this->checklist->completePreviousItem();

        $this->checklist->addItem('Importing database dump into MySQL on remote machine');
        $this->importDatabaseDumpOnRemote($siteInstance, $remoteDumpFilepath, $database);
        $this->checklist->completePreviousItem();

        return Command::SUCCESS;
    }

    private function uploadDatabaseDump(
        SiteInstanceResponse $siteInstance,
        string $localFilepath,
        callable $outputCallback
    ): string {
        $envAlias = self::getEnvironmentAlias($siteInstance);
        $remoteFilepath = "/mnt/tmp/$envAlias/" . basename($localFilepath);
        $this->logger->debug("Uploading database dump to $remoteFilepath on remote machine");
        $this->localMachineHelper->checkRequiredBinariesExist(['rsync']);
        $command = [
            'rsync',
            '-tDvPhe',
            'ssh -o StrictHostKeyChecking=no',
            $localFilepath,
            $siteInstance->environment->codebase->vcs_url . ':' . $remoteFilepath,
        ];
        $process = $this->localMachineHelper->execute($command, $outputCallback, null, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException(
                'Could not upload local database dump: {message}',
                ['message' => $process->getOutput()]
            );
        }

        return $remoteFilepath;
    }

    private function importDatabaseDumpOnRemote(SiteInstanceResponse $siteInstance, string $remoteDumpFilepath, SiteInstanceDatabaseResponse $database): void
    {
        $this->logger->debug("Importing $remoteDumpFilepath to MySQL on remote machine");
        $command = "pv $remoteDumpFilepath --bytes --rate | gunzip | MYSQL_PWD=$database->databasePassword mysql --host={$this->getHostFromDatabaseResponse($siteInstance,$database)} --user=$database->databaseUser {$this->getNameFromDatabaseResponse($database)}";
        $process = $this->sshHelper->executeCommand($siteInstance->environment->codebase->vcs_url, [$command], ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL), null);
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException('Unable to import database on remote machine. {message}', ['message' => $process->getErrorOutput()]);
        }
    }

    private function getNameFromDatabaseResponse(SiteInstanceDatabaseResponse $database): string
    {
        return $database->databaseName;
    }
}
