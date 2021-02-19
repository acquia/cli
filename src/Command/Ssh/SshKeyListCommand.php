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

    $this->io->title('Cloud Platform keys with matching local keys');
    foreach ($local_keys as $local_index => $local_file) {
      foreach ($cloud_keys as $index => $cloud_key) {
        if (trim($local_file->getContents()) === trim($cloud_key->public_key)) {
          $sha256_hash = FingerprintGenerator::getFingerprint($cloud_key->public_key, 'sha256');
          $this->io->definitionList(
            ['Label' => $cloud_key->label],
            ['UUID' => $cloud_key->uuid],
            ['Local filename' => $local_file->getFilename()],
            ['sha256 hash' => $sha256_hash],
            ['md5 hash' => $cloud_key->fingerprint]
          );
          unset($cloud_keys[$index], $local_keys[$local_index]);
          break;
        }
      }
    }
    $this->io->title('Cloud Platform keys with no matching local keys');
    foreach ($cloud_keys as $index => $cloud_key) {
      $sha256_hash = FingerprintGenerator::getFingerprint($cloud_key->public_key, 'sha256');
      $this->io->definitionList(
        ['Label' => $cloud_key->label],
        ['UUID' => $cloud_key->uuid],
        ['Local filename' => 'none'],
        ['sha256 hash' => $sha256_hash],
        ['md5 hash' => $cloud_key->fingerprint]
      );
    }

    $this->io->title('Local keys with no matching Cloud Platform keys');
    foreach ($local_keys as $index => $local_file) {
      $sha256_hash = FingerprintGenerator::getFingerprint($local_file->getContents(), 'sha256');
      $md5_hash = FingerprintGenerator::getFingerprint($local_file->getContents(), 'md5');
      $this->io->definitionList(
        ['Label' => 'none'],
        ['UUID' => 'none'],
        ['Local filename' => $local_file->getFilename()],
        ['sha256 hash' => $sha256_hash],
        ['md5 hash' => $md5_hash]
      );
    }

    return 0;
  }

}
