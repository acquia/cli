<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pull;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Transformer\EnvironmentTransformer;
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

        if ($input->hasOption('siteInstanceId') && $input->getOption('siteInstanceId')) {
            $siteInstance = $this->determineSiteInstance($input, $output, true);
            if ($siteInstance && $siteInstance->environment && $siteInstance->environment->codebase_uuid) {
                $sourceEnvironment = EnvironmentTransformer::transform($siteInstance->environment);
                $sourceEnvironment->vcs->url = $siteInstance->environment->codebase->vcs_url ?? $sourceEnvironment->vcs->url;
            } else {
                $sourceEnvironment = $this->determineEnvironment($input, $output, true);
            }
        } elseif ($input->hasOption('codebaseUuid') && $input->getOption('codebaseUuid')) {
            $codebase = $this->getCodebase($input->getOption('codebaseUuid'));
            $sourceEnvironment = $this->determineEnvironment($input, $output, true);
            $sourceEnvironment->vcs->url = $codebase->vcs_url ?? $sourceEnvironment->vcs->url;
        } else {
            $sourceEnvironment = $this->determineEnvironment($input, $output, true);
        }
        $this->pullFiles($input, $output, $sourceEnvironment);

        return Command::SUCCESS;
    }
}
