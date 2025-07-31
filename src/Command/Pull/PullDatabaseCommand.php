<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pull;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Attribute\RequireLocalDb;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[RequireLocalDb]
#[AsCommand(name: 'pull:database', description: 'Import database backup from a Cloud Platform environment', aliases: ['pull:db'])]
final class PullDatabaseCommand extends PullCommandBase
{
    protected function configure(): void
    {
        $this
            ->setHelp('This uses the latest available database backup, which may be up to 24 hours old. If no backup exists, one will be created.')
            ->acceptEnvironmentId()
            ->acceptSite()
            ->acceptSiteInstanceId()
            ->acceptCodebaseUuid()
            ->addOption(
                'no-scripts',
                null,
                InputOption::VALUE_NONE,
                'Do not run any additional scripts after the database is pulled. E.g., drush cache-rebuild, drush sql-sanitize, etc.'
            )
            ->addOption(
                'on-demand',
                null,
                InputOption::VALUE_NONE,
                'Force creation of an on-demand backup. This takes much longer than using an existing backup (when one is available)'
            )
            ->addOption(
                'no-import',
                null,
                InputOption::VALUE_NONE,
                'Download the backup but do not import it (implies --no-scripts)'
            )
            ->addOption(
                'multiple-dbs',
                null,
                InputOption::VALUE_NONE,
                'Download multiple dbs. Defaults to FALSE.'
            );
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $noScripts = $input->hasOption('no-scripts') && $input->getOption('no-scripts');
        $onDemand = $input->hasOption('on-demand') && $input->getOption('on-demand');
        $noImport = $input->hasOption('no-import') && $input->getOption('no-import');
        $multipleDbs = $input->hasOption('multiple-dbs') && $input->getOption('multiple-dbs');
        // $noImport implies $noScripts.
        $noScripts = $noImport || $noScripts;
        $this->setDirAndRequireProjectCwd($input);

        $sourceEnvironment = $this->determineEnvironment($input, $output, true);
        $this->pullDatabase($input, $output, $sourceEnvironment, $onDemand, $noImport, $multipleDbs);
        if (!$noScripts) {
            $this->runDrushCacheClear($this->getOutputCallback($output, $this->checklist), $this->checklist);
            $this->runDrushSqlSanitize($this->getOutputCallback($output, $this->checklist), $this->checklist);
        }

        return Command::SUCCESS;
    }
}
