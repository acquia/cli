<?php

namespace Acquia\Cli\Command\Ssh;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SshKeyUploadCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:upload';

  protected function configure(): void {
    $this->setDescription('Upload a local SSH key to the Cloud Platform')
      ->addOption('filepath', NULL, InputOption::VALUE_REQUIRED, 'The filepath of the public SSH key to upload')
      ->addOption('label', NULL, InputOption::VALUE_REQUIRED, 'The SSH key label to be used with the Cloud Platform')
      ->addOption('no-wait', NULL, InputOption::VALUE_NONE, "Don't wait for the SSH key to be uploaded to the Cloud Platform");
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    [$chosenLocalKey, $publicKey] = $this->determinePublicSshKey();
    $label = $this->determineSshKeyLabel();
    $this->uploadSshKey($label, $publicKey);
    $this->io->success("Uploaded $chosenLocalKey to the Cloud Platform with label $label");

    return 0;
  }

}
