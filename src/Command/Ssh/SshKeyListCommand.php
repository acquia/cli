<?php

namespace Acquia\Cli\Command\Ssh;

use Symfony\Component\Console\Helper\Table;
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
    $table = $this->createSshKeyTable($output, 'Cloud Platform keys with matching local keys');
    foreach ($local_keys as $local_index => $local_file) {
      foreach ($cloud_keys as $index => $cloud_key) {
        if (trim($local_file->getContents()) === trim($cloud_key->public_key)) {
          $hash = FingerprintGenerator::getFingerprint($cloud_key->public_key, 'sha256');
          $table->addRow([
            $cloud_key->label,
            $local_file->getFilename(),
            $hash,
          ]);
          unset($cloud_keys[$index], $local_keys[$local_index]);
          break;
        }
      }
    }
    $table->render();
    $this->io->newLine();

    $table = $this->createSshKeyTable($output, 'Cloud Platform keys with no matching local keys');
    foreach ($cloud_keys as $index => $cloud_key) {
      $hash = FingerprintGenerator::getFingerprint($cloud_key->public_key, 'sha256');
      $table->addRow([
        $cloud_key->label,
        'none',
        $hash,
      ]);
    }
    $table->render();
    $this->io->newLine();

    $table = $this->createSshKeyTable($output, 'Local keys with no matching Cloud Platform keys');
    foreach ($local_keys as $index => $local_file) {
      $hash = FingerprintGenerator::getFingerprint($local_file->getContents(), 'sha256');
      $table->addRow([
        'none',
        $local_file->getFilename(),
        $hash,
      ]);
    }
    $table->render();

    return 0;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $title
   *
   * @return \Symfony\Component\Console\Helper\Table
   */
  private function createSshKeyTable(OutputInterface $output, string $title): Table {
    $headers = ['Cloud Platform label', 'Local filename', 'Fingerprint (sha256)'];
    $widths = [.4, .2, .2];
    return $this->createTable($output, $title, $headers, $widths);
  }

}
