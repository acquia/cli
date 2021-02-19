<?php

namespace Acquia\Cli\Command\Ssh;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use violuke\RsaSshKeyFingerprint\FingerprintGenerator;

/**
 * Class SshKeyListCommand.
 */
class SshKeyListCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:list';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('List your local and remote SSH keys');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $local_keys = $this->findLocalSshKeys();

    $table = new Table($output);
    $table->setHeaders([
      'Cloud platform key label and UUID',
      'Local key filename',
      'Hashes — sha256 and md5',
    ]);
    foreach ($local_keys as $local_index => $local_file) {
      foreach ($cloud_keys as $index => $cloud_key) {
        if (trim($local_file->getContents()) === trim($cloud_key->public_key)) {
            $hash = FingerprintGenerator::getFingerprint($cloud_key->public_key, 'sha256');
            $table->addRow([
              $cloud_key->label . PHP_EOL . $cloud_key->uuid,
              $local_file->getFilename(),
              $hash . PHP_EOL . $cloud_key->fingerprint,
            ]);
            $table->addRow(new TableSeparator());
          unset($cloud_keys[$index], $local_keys[$local_index]);
          break;
        }
      }
    }
    foreach ($cloud_keys as $index => $cloud_key) {
      $hash = FingerprintGenerator::getFingerprint($cloud_key->public_key, 'sha256');
      $table->addRow([
        $cloud_key->label . PHP_EOL . $cloud_key->uuid,
        'none',
        $hash . PHP_EOL . $cloud_key->fingerprint,
      ]);
      $table->addRow(new TableSeparator());
    }

    foreach ($local_keys as $local_file) {
      $table->addRow(['none', $local_file->getFilename(), '']);
    }
    $table->render();

    return 0;
  }

}
