<?php

namespace Acquia\Cli\Command\Ssh;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SshKeyCreateUploadCommand extends SshKeyCreateCommand {

  protected static $defaultName = 'ssh-key:create-upload';

  protected function configure(): void {
    $this->setDescription('Create an SSH key on your local machine and upload it to the Cloud Platform')
      ->addOption('filename', NULL, InputOption::VALUE_REQUIRED, 'The filename of the SSH key')
      ->addOption('password', NULL, InputOption::VALUE_REQUIRED, 'The password for the SSH key')
      ->addOption('label', NULL, InputOption::VALUE_REQUIRED, 'The SSH key label to be used with the Cloud Platform')
      ->addOption('no-wait', NULL, InputOption::VALUE_NONE, "Don't wait for the SSH key to be uploaded to the Cloud Platform");
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
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
