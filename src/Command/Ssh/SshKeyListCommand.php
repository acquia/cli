<?php

namespace Acquia\Ads\Command\Ssh;

use Acquia\Ads\AcquiaCliApplication;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SshKeyListCommand.
 */
class SshKeyListCommand extends SshKeyCommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ssh-key:list')->setDescription('List your local and remote SSH keys');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $local_keys = $this->findLocalSshKeys();

    $table = new Table($output);
    $table->setHeaders(['Local Key Filename', 'Acquia Cloud Key Label']);
    foreach ($local_keys as $local_index => $local_file) {
      foreach ($cloud_keys as $index => $cloud_key) {
        if (trim($local_file->getContents()) === trim($cloud_key->public_key)) {
          $table->addRow([$local_file->getFilename(), $cloud_key->label]);
          unset($cloud_keys[$index], $local_keys[$local_index]);
          break;
        }
      }
    }
    foreach ($cloud_keys as $index => $cloud_key) {
      $table->addRow(['---', $cloud_key->label]);
    }
    foreach ($local_keys as $local_file) {
      $table->addRow([$local_file->getFilename(), '---']);
    }
    $table->render();

    return 0;
  }

}
