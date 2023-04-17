<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Helpers\SshCommandTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SshKeyDeleteCommand.
 */
class SshKeyDeleteCommand extends SshKeyCommandBase {

  use SshCommandTrait;

  protected static $defaultName = 'ssh-key:delete';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Delete an SSH key')
      ->addOption('cloud-key-uuid', 'uuid', InputOption::VALUE_REQUIRED);
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->deleteSshKeyFromCloud($output);
  }

}
