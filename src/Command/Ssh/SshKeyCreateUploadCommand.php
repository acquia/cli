<?php

namespace Acquia\Cli\Command\Ssh;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SshKeyCreateCommand.
 */
class SshKeyCreateUploadCommand extends SshKeyCreateCommand {

  protected static $defaultName = 'ssh-key:create-upload';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Create an SSH key on your local machine and upload it to the Cloud Platform')
      ->addOption('filename', NULL, InputOption::VALUE_REQUIRED, 'The filename of the SSH key')
      ->addOption('password', NULL, InputOption::VALUE_REQUIRED, 'The password for the SSH key')
      ->addOption('no-wait', NULL, InputOption::VALUE_NONE, "Don't wait for the SSH key to be uploaded to the Cloud Platform");
  }

  /**
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $filename = $this->determineFilename($input);
    $password = $this->determinePassword($input);
    $this->createSshKey($filename, $password);
    $public_key = $this->localMachineHelper->readFile($this->publicSshKeyFilepath);
    $chosen_local_key = basename($this->privateSshKeyFilepath);
    $label = $this->determineSshKeyLabel($input);
    $this->uploadSshKey($label, $public_key);
    $this->io->success("Uploaded $chosen_local_key to the Cloud Platform with label $label");

    return 0;
  }

}
