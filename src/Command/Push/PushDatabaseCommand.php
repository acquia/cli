<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Attribute\RequireLocalDb;
use Acquia\Cli\Attribute\RequireRemoteDb;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Response\DatabaseResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[RequireAuth]
#[RequireLocalDb]
#[RequireRemoteDb]
#[AsCommand(name: 'push:database', description: 'Push a database from your local environment to a Cloud Platform environment', aliases: ['push:db'])]
final class PushDatabaseCommand extends PushCommandBase
{
    private const STREAM_PROGRESS_INTERVAL_BYTES = 1048576;

    protected function configure(): void
    {
        $this
            ->acceptEnvironmentId()
            ->acceptSite()
            ->acceptSiteInstanceId();
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $destinationEnvironment = $this->determineEnvironment($input, $output);

        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $databases = $this->determineCloudDatabases($acquiaCloudClient, $destinationEnvironment, $input->getArgument('site'));
        // We only support pushing a single database.
        $database = $databases[0];
        if ($database->user_name === null) {
            throw new AcquiaCliException('Database connection details missing');
        }
        $answer = $this->io->confirm("Overwrite the <bg=cyan;options=bold>$database->name</> database on <bg=cyan;options=bold>$destinationEnvironment->name</> with a copy of the database from the current machine?");
        if (!$answer) {
            return Command::SUCCESS;
        }

        $this->checklist = new Checklist($output);
        $outputCallback = $this->getOutputCallback($output, $this->checklist);

        if ($this->canUseUploadTransport($destinationEnvironment)) {
            $this->checklist->addItem('Creating local database dump');
            $localDumpFilepath = $this->createMySqlDumpOnLocal($this->getLocalDbHost(), $this->getLocalDbUser(), $this->getLocalDbName(), $this->getLocalDbPassword(), $outputCallback);
            $this->checklist->completePreviousItem();

            $this->checklist->addItem('Uploading database dump to remote machine');
            $remoteDumpFilepath = $this->uploadDatabaseDump($destinationEnvironment, $localDumpFilepath, $outputCallback);
            $this->checklist->completePreviousItem();

            $this->checklist->addItem('Importing database dump into MySQL on remote machine');
            $this->importDatabaseDumpOnRemote($destinationEnvironment, $remoteDumpFilepath, $database);
            $this->checklist->completePreviousItem();

            return Command::SUCCESS;
        }

        $this->assertStreamedTransportCanTargetDatabase($acquiaCloudClient, $destinationEnvironment);
        $this->streamDatabaseToRemote($destinationEnvironment, $outputCallback);

        return Command::SUCCESS;
    }

    /**
     * Decide whether the upload-and-import transport can be used at all.
     *
     * Capability only; no subscription type or operating system is consulted.
     * The local half is checked first to avoid a wasted round trip.
     */
    private function canUseUploadTransport(EnvironmentResponse $environment): bool
    {
        foreach (['rsync', 'gzip', 'bash'] as $binary) {
            if (!$this->localMachineHelper->commandExists($binary)) {
                $this->logger->debug("Streaming the import: the local `$binary` binary is missing");
                return false;
            }
        }

        // A policy denial is a completed exec with a non-zero status. SSH's own
        // failures are 255, which SshHelper raises rather than downgrading on.
        $probe = $this->sshHelper->executeCommandWithoutTty($environment->sshUrl, ['bash', '-c', 'true'], false);
        if (!$probe->isSuccessful()) {
            $this->logger->debug('Streaming the import: the environment does not permit a shell command');
            return false;
        }

        return true;
    }

    /**
     * Refuse the streamed transport where it could overwrite the wrong database.
     *
     * Drush resolves the connection from the site URI while the prompt names a
     * database from the Cloud API; on a multisite those can disagree.
     */
    private function assertStreamedTransportCanTargetDatabase(Client $acquiaCloudClient, EnvironmentResponse $environment): void
    {
        $databases = $this->getEnvironmentDatabases($acquiaCloudClient, $environment->uuid);
        if (count($databases) > 1) {
            throw new AcquiaCliException('Cannot push a database to a multisite environment that does not permit shell commands. The streamed import resolves the target site from its URI, which may not be the database you selected.');
        }
    }

    /**
     * Stream a plain-SQL dump into `drush sql:cli` on the remote environment.
     *
     * Two processes joined in PHP, so there is no local shell and the two exit
     * statuses stay independent.
     */
    private function streamDatabaseToRemote(EnvironmentResponse $environment, callable $outputCallback): void
    {
        $this->localMachineHelper->checkRequiredBinariesExist(['mysqldump']);
        $this->checklist->addItem('Streaming local database dump into the remote database');

        $dumpCommand = self::buildStreamingMySqlDumpCommand(
            $this->getLocalDbHost(),
            $this->getLocalDbUser(),
            $this->getLocalDbName(),
            $this->getMySqlDumpHelp()
        );
        $dump = $this->localMachineHelper->createProcess($dumpCommand, ['MYSQL_PWD' => $this->getLocalDbPassword()]);
        // A large dump takes far longer than the default 60s.
        $dump->setTimeout(null);
        $dump->start();

        $remoteCommand = self::buildStreamedSqlCliCommand(
            self::getEnvironmentAlias($environment),
            $environment->default_domain
        );
        $import = $this->sshHelper->executeCommandWithoutTty(
            $environment->sshUrl,
            $remoteCommand,
            // Not gated on verbosity: without a pty this is the import's only
            // channel, and a success prints nothing.
            true,
            null,
            null,
            $this->streamWithProgress($dump, $outputCallback)
        );
        $this->checklist->completePreviousItem();

        // The dump is checked first: it can fail mid-stream while the import
        // exits 0 on the truncated input it received.
        if (!$dump->isSuccessful()) {
            // `sql:cli` already ran the statements it received, so the remote
            // is half-written even though the import exited zero.
            throw new AcquiaCliException('Unable to create a dump of the local database, and the remote database may have been partially overwritten before the dump failed. {message}', ['message' => $dump->getErrorOutput()]);
        }
        if (!$import->isSuccessful()) {
            throw new AcquiaCliException('Unable to import the database on the remote machine. {message}', ['message' => $import->getErrorOutput()]);
        }
    }

    /**
     * Yield the dump's output, reporting how much has been sent.
     *
     * Counted here rather than with `pv`, which does not exist on Windows.
     *
     * @return \Generator<string>
     */
    private function streamWithProgress(Process $dump, callable $outputCallback): \Generator
    {
        $sent = 0;
        $nextReport = self::STREAM_PROGRESS_INTERVAL_BYTES;

        foreach ($dump->getIterator(Process::ITER_SKIP_ERR) as $chunk) {
            $sent += strlen($chunk);
            if ($sent >= $nextReport) {
                $outputCallback('out', sprintf('Streamed %d MB so far', intdiv($sent, self::STREAM_PROGRESS_INTERVAL_BYTES)));
                $nextReport = $sent + self::STREAM_PROGRESS_INTERVAL_BYTES;
            }

            yield $chunk;
        }

        $outputCallback('out', sprintf('Streamed %d MB in total', intdiv($sent, self::STREAM_PROGRESS_INTERVAL_BYTES)));
    }

    /**
     * Read the local client's advertised options, for flag selection.
     *
     * An unreadable `--help` yields no conditional flags, which is not fatal.
     */
    private function getMySqlDumpHelp(): string
    {
        return $this->localMachineHelper->execute(['mysqldump', '--help'], null, null, false)->getOutput();
    }

    private function uploadDatabaseDump(
        EnvironmentResponse $environment,
        string $localFilepath,
        callable $outputCallback
    ): string {
        $envAlias = self::getEnvironmentAlias($environment);
        $remoteFilepath = "/mnt/tmp/$envAlias/" . basename($localFilepath);
        $this->logger->debug("Uploading database dump to $remoteFilepath on remote machine");
        $this->localMachineHelper->checkRequiredBinariesExist(['rsync']);
        $command = [
            'rsync',
            '-tDvPhe',
            'ssh -o StrictHostKeyChecking=accept-new',
            $localFilepath,
            $environment->sshUrl . ':' . $remoteFilepath,
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

    /**
     * Import database dump on remote server.
     * Manually escapes single quotes for bash command safety.
     */
    private function importDatabaseDumpOnRemote(EnvironmentResponse $environment, string $remoteDumpFilepath, DatabaseResponse $database): void
    {
        $this->logger->debug("Importing $remoteDumpFilepath to MySQL on remote machine");
        // Manually escape for single-quoted bash strings (replace ' with '\'' - end quote, escaped quote, start quote)
        $host = str_replace("'", "'\\''", $this->getHostFromDatabaseResponse($environment, $database));
        $user = str_replace("'", "'\\''", $database->user_name);
        $dbName = str_replace("'", "'\\''", $this->getNameFromDatabaseResponse($database));
        $password = str_replace("'", "'\\''", $database->password);
        // @infection-ignore-all System-generated path under our control, defensive escaping
        $filepath = str_replace("'", "'\\''", $remoteDumpFilepath);
        $command = "bash -o pipefail -c 'pv '$filepath' --bytes --rate | gunzip | MYSQL_PWD='$password' mysql --host='$host' --user='$user' '$dbName''";
        $process = $this->sshHelper->executeCommand($environment->sshUrl, [$command], ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException('Unable to import database on remote machine. {message}', ['message' => $process->getErrorOutput()]);
        }
    }

    private function getNameFromDatabaseResponse(DatabaseResponse $database): string
    {
        $dbUrlParts = explode('/', $database->url);
        return end($dbUrlParts);
    }
}
