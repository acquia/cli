<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Attribute\RequireAuth;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'ssh-key:upload', description: 'Upload a local SSH key to the Cloud Platform')]
final class SshKeyUploadCommand extends SshKeyCommandBase
{
    protected function configure(): void
    {
        $this
        ->addOption('filepath', null, InputOption::VALUE_REQUIRED, 'The filepath of the public SSH key to upload')
        ->addOption('label', null, InputOption::VALUE_REQUIRED, 'The SSH key label to be used with the Cloud Platform')
        ->addOption('no-wait', null, InputOption::VALUE_NONE, "Don't wait for the SSH key to be uploaded to the Cloud Platform");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$chosenLocalKey, $publicKey] = $this->determinePublicSshKey();
        $label = $this->determineSshKeyLabel();
        $this->uploadSshKey($label, $publicKey);
        $this->io->success("Uploaded $chosenLocalKey to the Cloud Platform with label $label");

        return Command::SUCCESS;
    }
}
