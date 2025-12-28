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
#[AsCommand(name: 'ssh-key:create-upload', description: 'Create an SSH key on your local machine and upload it to the Cloud Platform (Added in 1.0.0)')]
final class SshKeyCreateUploadCommand extends SshKeyCommandBase
{
    protected function configure(): void
    {
        $this
            ->addOption('filename', null, InputOption::VALUE_REQUIRED, 'The filename of the SSH key')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'The password for the SSH key')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'The SSH key label to be used with the Cloud Platform')
            ->addOption('no-wait', null, InputOption::VALUE_NONE, "Don't wait for the SSH key to be uploaded to the Cloud Platform");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $this->determineFilename();
        $password = $this->determinePassword();
        $this->createSshKey($filename, $password);
        $publicKey = $this->localMachineHelper->readFile($this->publicSshKeyFilepath);
        $chosenLocalKey = basename($this->privateSshKeyFilepath);
        $label = $this->determineSshKeyLabel();
        $this->uploadSshKey($label, $publicKey);
        $this->io->success("Uploaded $chosenLocalKey to the Cloud Platform with label $label");

        return Command::SUCCESS;
    }
}
