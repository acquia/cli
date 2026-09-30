<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pull;

use Acquia\Cli\Attribute\RequireAuth;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'pull:files-archive', description: 'Copy Drupal public files from a Cloud Platform environment to your local environment as a tar archive streamed over SSH (does not require rsync)')]
final class PullFilesArchiveCommand extends PullCommandBase
{
    protected function configure(): void
    {
        $this
            ->acceptEnvironmentId()
            ->acceptSite()
            ->acceptSiteInstanceId();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setDirAndRequireProjectCwd($input);

        $sourceEnvironment = $this->determineEnvironment($input, $output, true);

        $this->pullFilesArchive($input, $output, $sourceEnvironment);

        return Command::SUCCESS;
    }
}
