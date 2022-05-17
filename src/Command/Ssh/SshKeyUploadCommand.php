<?php

namespace Acquia\Cli\Command\Ssh;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SshKeyUploadCommand.
 */
class SshKeyUploadCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:upload';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Upload a local SSH key to the Cloud Platform')
      ->addOption('filepath', NULL, InputOption::VALUE_REQUIRED, 'The filepath of the public SSH key to upload')
      ->addOption('label', NULL, InputOption::VALUE_REQUIRED, 'The SSH key label to be used with the Cloud Platform')
      ->addOption('no-wait', NULL, InputOption::VALUE_NONE, "Don't wait for the SSH key to be uploaded to the Cloud Platform");
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    return TRUE;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    [$chosen_local_key, $public_key] = $this->determinePublicSshKey();
    $label = $this->determineSshKeyLabel($input, $output);
    $this->uploadSshKey($label, $chosen_local_key, $public_key);

    return 0;
  }

}
