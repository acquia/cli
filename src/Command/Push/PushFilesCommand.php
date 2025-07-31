<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'push:files', description: 'Copy Drupal public files from your local environment to a Cloud Platform environment')]
final class PushFilesCommand extends PushCommandBase
{
    protected function configure(): void
    {
        $this
            ->acceptEnvironmentId()
            ->acceptSite()
            ->acceptCodebaseUuid()
            ->acceptSiteInstanceId();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setDirAndRequireProjectCwd($input);

        $destinationEnvironment = $this->determineEnvironment($input, $output);
        $chosenSite = $input->getArgument('site');
        if (!$chosenSite) {
            $chosenSite = $this->promptChooseDrupalSite($destinationEnvironment);
        }
        $answer = $this->io->confirm("Overwrite the public files directory on <bg=cyan;options=bold>$destinationEnvironment->name</> with a copy of the files from the current machine?");
        if (!$answer) {
            return Command::SUCCESS;
        }

        $this->checklist = new Checklist($output);
        $this->checklist->addItem('Pushing public files directory to remote machine');
        $this->rsyncFilesToCloud($destinationEnvironment, $this->getOutputCallback($output, $this->checklist), $chosenSite);
        $this->checklist->completePreviousItem();

        return Command::SUCCESS;
    }

    private function rsyncFilesToCloud(EnvironmentResponse $chosenEnvironment, ?callable $outputCallback = null, ?string $site = null): void
    {
        $sourceDir = $this->getLocalFilesDir($site);
        $destinationDir = $chosenEnvironment->sshUrl . ':' . $this->getCloudFilesDir($chosenEnvironment, $site);

        $this->rsyncFiles($sourceDir, $destinationDir, $outputCallback);
    }
}
