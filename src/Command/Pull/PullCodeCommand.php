<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pull;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Attribute\RequireDb;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[RequireDb]
#[AsCommand(name: 'pull:code', description: 'Copy code from a Cloud Platform environment')]
final class PullCodeCommand extends PullCommandBase
{
    protected function configure(): void
    {
        $this
        ->acceptEnvironmentId()
        ->addOption('dir', null, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed')
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
        $sourceEnvironment = $this->determineEnvironment($input, $output, true);
        $this->pullCode($input, $output, $clone, $sourceEnvironment);
        $this->checkEnvironmentPhpVersions($sourceEnvironment);
        $this->matchIdePhpVersion($output, $sourceEnvironment);
        if (!$input->getOption('no-scripts')) {
            $outputCallback = $this->getOutputCallback($output, $this->checklist);
            $this->runComposerScripts($outputCallback, $this->checklist);
            $this->runDrushCacheClear($outputCallback, $this->checklist);
        }

        return Command::SUCCESS;
    }
}
