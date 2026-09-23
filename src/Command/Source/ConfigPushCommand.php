<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Helpers\SourceConfigDocument;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Exception\ApiErrorException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[RequireAuth]
#[AsCommand(name: 'source:cms:config:push', description: 'Import .acquia/config into a Source site')]
final class ConfigPushCommand extends ConfigCommandBase
{
    /**
     * --status's exit code while the site's latest import has not finished;
     * distinct from Command::SUCCESS/FAILURE so a CI/CD run can poll on it.
     */
    private const EXIT_STILL_RUNNING = 2;

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the confirmation prompt (required when non-interactive)');
        $this->addOption('status', null, InputOption::VALUE_NONE, "Report the site's latest import instead of starting one");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = $this->outputsJson();
        $root = $this->workingCopyDir();
        $siteId = $this->determineSourceSite($root);
        $client = $this->cloudApiClientService->getClient();

        if ($input->getOption('status')) {
            $import = $client->request('get', "/source-sites/$siteId/config/import");
            return $this->reportResult($input, $output, $json, $import, fn (object $import): int => $this->reportStatus($siteId, $import));
        }

        $configDir = "$root/.acquia/config";
        if (!is_dir($configDir)) {
            throw new AcquiaCliException('{dir} does not exist. Run acli source:cms:config:pull first.', ['dir' => $configDir]);
        }
        $document = SourceConfigDocument::fromDirectory($configDir);

        if (!$input->getOption('yes')) {
            if (!$input->isInteractive()) {
                throw new AcquiaCliException('Pass --yes to push without confirmation when running non-interactively.');
            }
            if (!$this->io->confirm("Replace the configuration of Source site $siteId with the contents of $configDir? The site will be offline while the import runs, and its database is backed up first.")) {
                return Command::SUCCESS;
            }
        }

        try {
            $client->request('put', "/source-sites/$siteId/config", ['json' => ['configuration' => $document]]);
        } catch (ApiErrorException $e) {
            // 409: the site is already importing (or exporting) configuration.
            if ($e->getResponseBody()->error === 'conflict') {
                throw new AcquiaCliException('A configuration sync is already running for Source site {site}. Wait for it to finish, then push again.', ['site' => $siteId]);
            }
            throw $e;
        }

        if ($json) {
            // The import resource is the only stdout: silence the spinner and the report.
            $this->output = new NullOutput();
            $this->io = new SymfonyStyle($input, $this->output);
        }
        $import = $this->waitForImport($client, $siteId);
        return $this->reportResult($input, $output, $json, $import, fn (object $import): int => $this->reportImport($siteId, $import));
    }

    /**
     * Reports $import via $report, printing it as JSON afterward when $json.
     *
     * @param callable(object): int $report
     */
    private function reportResult(InputInterface $input, OutputInterface $output, bool $json, object $import, callable $report): int
    {
        if ($json) {
            $this->output = new NullOutput();
            $this->io = new SymfonyStyle($input, $this->output);
        }
        $exitCode = $report($import);
        if ($json) {
            $output->writeln(json_encode($import, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }
        return $exitCode;
    }

    /**
     * Polls the site's latest import until it is no longer running.
     */
    private function waitForImport(Client $client, string $siteId): object
    {
        $import = null;
        $error = null;
        $poll = static function () use ($client, $siteId, &$import, &$error): bool {
            try {
                $import = $client->request('get', "/source-sites/$siteId/config/import");
                return $import->status !== 'running';
            } catch (Throwable $e) {
                // An exception escaping the loop would leave its timers behind.
                $error = $e;
                return true;
            }
        };
        // getLoopy() requires a done callback; the outcome is read from $import
        // and $error above once it returns, so there is nothing to do here.
        LoopHelper::getLoopy($this->output, $this->io, 'Importing configuration', $poll, static function (): void {
        });
        if ($error !== null) {
            throw new AcquiaCliException('The import was accepted but its outcome is unknown ({reason}). Check the site before pushing again. The import may still finish, and the status it reports may then be that of a later import.', ['reason' => $error->getMessage()]);
        }
        return $import;
    }

    /**
     * Reports the site's latest import for --status, without waiting for it to finish.
     */
    private function reportStatus(string $siteId, object $import): int
    {
        if ($import->status === 'running') {
            $this->io->writeln("Source site $siteId's latest import started at $import->started_at and is still running.");
            return self::EXIT_STILL_RUNNING;
        }
        $this->io->writeln("Source site $siteId's latest import started at $import->started_at.");
        return $this->reportImport($siteId, $import);
    }

    private function reportImport(string $siteId, object $import): int
    {
        switch ($import->status) {
            case 'succeeded':
                $this->io->success("Imported .acquia/config into Source site $siteId.");
                return Command::SUCCESS;

            case 'refused':
                $this->io->error("Source site $siteId refused the configuration; nothing was imported:");
                foreach ($import->violations ?? [] as $violation) {
                    $location = match (true) {
                        isset($violation->collection) => "$violation->collection: $violation->config",
                        isset($violation->config) => $violation->config,
                        default => 'document',
                    };
                    $this->io->writeln(sprintf(' - %s [%s]: %s', $location, $violation->code, $violation->message));
                }
                return Command::FAILURE;

            case 'failed':
                $this->io->error("The import into Source site $siteId failed; the site was rolled back to its previous configuration.");
                return Command::FAILURE;
        }
        // Only the 45-minute watchdog in LoopHelper can leave the status at "running".
        throw new AcquiaCliException('The import was accepted but its outcome is unknown (status {status}). Check the site before pushing again. The import may still finish, and the status it reports may then be that of a later import.', ['status' => $import->status]);
    }
}
