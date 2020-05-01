<?php

namespace Acquia\Ads\Command\Ssh;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SshKeyCreateCommand.
 */
class SshKeyCreateUploadCommand extends SshKeyCreateCommand {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ssh-key:create-upload')
      ->setDescription('Create an SSH key on your local machine and upload it to Acquia Cloud');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $private_ssh_key_filepath = $this->createSshKey();
    $public_ssh_key_filepath = $private_ssh_key_filepath . '.pub';

    $command = $this->getApplication()->find('ssh-key:upload');
    $arguments = [
      'command' => 'ssh-key:upload',
      '--filepath' => $public_ssh_key_filepath,
    ];
    $list_input = new ArrayInput($arguments);

    return $command->run($list_input, $output);
  }

}
