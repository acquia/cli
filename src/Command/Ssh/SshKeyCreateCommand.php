<?php

namespace Acquia\Cli\Command\Ssh;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SshKeyCreateCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:create';

  protected function configure(): void {
    $this->setDescription('Create an SSH key on your local machine')
      ->addOption('filename', NULL, InputOption::VALUE_REQUIRED, 'The filename of the SSH key')
      ->addOption('password', NULL, InputOption::VALUE_REQUIRED, 'The password for the SSH key');
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $filename = $this->determineFilename();
    $password = $this->determinePassword();
    $this->createSshKey($filename, $password);
    $output->writeln('<info>Created new SSH key.</info> ' . $this->publicSshKeyFilepath);

    return 0;
  }

}
