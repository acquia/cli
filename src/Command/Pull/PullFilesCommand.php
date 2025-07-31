<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pull;

use Acquia\Cli\Attribute\RequireAuth;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'pull:files', description: 'Copy Drupal public files from a Cloud Platform environment to your local environment')]
final class PullFilesCommand extends PullCommandBase
{
    protected function configure(): void
    {
        $this
            ->acceptEnvironmentId()
            ->acceptSite()
            ->acceptSiteInstanceId()
            ->acceptCodebaseUuid();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setDirAndRequireProjectCwd($input);

        $sourceEnvironment = $this->determineEnvironment($input, $output);

        $this->pullFiles($input, $output, $sourceEnvironment);

        return Command::SUCCESS;
    }
}
