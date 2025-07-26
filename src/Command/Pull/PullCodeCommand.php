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
#[AsCommand(name: 'pull:code', description: 'Copy code from a Cloud Platform environment')]
final class PullCodeCommand extends PullCommandBase
{
    protected function configure(): void
    {
        $this
            ->acceptSiteInstanceId()
            ->addOption('dir', null, InputOption::VALUE_OPTIONAL, 'The directory containing the Drupal project to be refreshed')
            ->addOption(
                'no-scripts',
                null,
                InputOption::VALUE_NONE,
                'Do not run any additional scripts after code is pulled. E.g., composer install , drush cache-rebuild, etc.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setDirAndRequireProjectCwd($input);
        $clone = $this->determineCloneProject($output);
        $siteInstance = $this->determineSiteInstance($input, $output, true);
        $this->pullCode($input, $output, $clone, $siteInstance);
        $this->checkEnvironmentPhpVersions($siteInstance);
        $this->matchIdePhpVersion($output, $siteInstance);
        if (!$input->getOption('no-scripts')) {
            $outputCallback = $this->getOutputCallback($output, $this->checklist);
            $this->runComposerScripts($outputCallback, $this->checklist);
            $this->runDrushCacheClear($outputCallback, $this->checklist);
        }

        return Command::SUCCESS;
    }
}
