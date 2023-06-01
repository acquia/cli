<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Helpers\SshCommandTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SshKeyDeleteCommand extends SshKeyCommandBase {

  use SshCommandTrait;

  // phpcs:ignore
  protected static $defaultName = 'ssh-key:delete';

  protected function configure(): void {
    $this->setDescription('Delete an SSH key')
      ->addOption('cloud-key-uuid', 'uuid', InputOption::VALUE_REQUIRED);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->deleteSshKeyFromCloud($output);
  }

}
