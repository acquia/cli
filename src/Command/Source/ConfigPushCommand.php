<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Helpers\SourceConfigDocument;
use AcquiaCloudApi\Connector\Client;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[RequireAuth]
#[AsCommand(name: 'source:cms:config:push', description: 'Import .acquia/config into a Source site')]
final class ConfigPushCommand extends ConfigCommandBase
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip the confirmation prompt (required when non-interactive)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = $this->outputsJson();
        $root = $this->workingCopyDir();
        $siteId = $this->determineSourceSite($root);
        $configDir = "$root/.acquia/config";
        if (!is_dir($configDir)) {
            throw new AcquiaCliException('{dir} does not exist. Run acli source:cms:config:pull first.', ['dir' => $configDir]);
        }
        $files = [];
        foreach ($this->localMachineHelper->getFinder()->files()->in($configDir)->name('*.yml') as $file) {
            $files[$file->getRelativePathname()] = $file->getContents();
        }
        $document = SourceConfigDocument::fromFiles($files);

        if (!$input->getOption('force')) {
            if (!$input->isInteractive()) {
                throw new AcquiaCliException('Pass --force to push without confirmation when running non-interactively.');
            }
            if (!$this->io->confirm("Replace the configuration of Source site $siteId with the contents of $configDir? The site is put in maintenance mode and its database is backed up first.")) {
                return Command::SUCCESS;
            }
        }

        $client = $this->cloudApiClientService->getClient();
        $client->request('put', "/source-sites/$siteId/config", ['json' => ['configuration' => $document]]);

        if ($json) {
            // The import resource is the only stdout: silence the spinner and the report.
            $this->output = new NullOutput();
            $this->io = new SymfonyStyle($input, $this->output);
        }
        $import = $this->waitForImport($client, $siteId);
        $exitCode = $this->reportImport($import);
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
            } catch (Exception $e) {
                // An exception escaping the loop would leave its timers behind.
                $error = $e;
                return true;
            }
        };
        LoopHelper::getLoopy($this->output, $this->io, 'Importing configuration', $poll, static function (): void {
        });
        if ($error !== null) {
            throw new AcquiaCliException('The import was accepted but its outcome is unknown ({reason}). Check the site before pushing again.', ['reason' => $error->getMessage()]);
        }
        return $import;
    }

    private function reportImport(object $import): int
    {
        switch ($import->status) {
            case 'succeeded':
                $this->io->success('Configuration imported.');
                return Command::SUCCESS;

            case 'refused':
                $this->io->error('The import was refused; the site is unchanged.');
                foreach ($import->violations ?? [] as $violation) {
                    $location = trim(($violation->collection ?? '') . ' ' . ($violation->config ?? '')) ?: 'document';
                    $this->io->writeln(" - $location [$violation->code]: $violation->message");
                }
                return Command::FAILURE;

            case 'failed':
                $this->io->error('The import failed and the site was restored from its backup.');
                return Command::FAILURE;
        }
        // Only the 45-minute watchdog in LoopHelper can leave the status at "running".
        throw new AcquiaCliException('The import was accepted but its outcome is unknown (status {status}). Check the site before pushing again.', ['status' => $import->status]);
    }
}
