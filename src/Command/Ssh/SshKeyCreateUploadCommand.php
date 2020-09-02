<?php

namespace Acquia\Cli\Command\Ssh;

use Symfony\Component\Console\Input\ArrayInput;
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
  protected function configure() {
    $this->setDescription('Create an SSH key on your local machine and upload it to the Cloud Platform')
      ->addOption('filename', NULL, InputOption::VALUE_REQUIRED, 'The filename of the SSH key')
      ->addOption('password', NULL, InputOption::VALUE_REQUIRED, 'The password for the SSH key')
    ->addOption('no-wait', NULL, InputOption::VALUE_NONE, "Don't wait for the SSH key to be uploaded to the Cloud Platform");
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $private_ssh_key_filepath = $this->createSshKey($input, $output);
    $public_ssh_key_filepath = $private_ssh_key_filepath . '.pub';

    $command = $this->getApplication()->find('ssh-key:upload');
    $arguments = [
      'command' => 'ssh-key:upload',
      '--filepath' => $public_ssh_key_filepath,
      '--no-wait' => $input->getOption('no-wait'),
    ];
    $list_input = new ArrayInput($arguments);

    return $command->run($list_input, $output);
  }

}
