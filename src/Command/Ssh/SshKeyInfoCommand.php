<?php

namespace Acquia\Cli\Command\Ssh;

use AcquiaCloudApi\Endpoints\SshKeys;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use violuke\RsaSshKeyFingerprint\FingerprintGenerator;

class SshKeyInfoCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:info';

  protected function configure(): void {
    $this->setDescription('Print information about an SSH key')
      ->addOption('fingerprint', NULL, InputOption::VALUE_REQUIRED);
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $key = $this->determineSshKey($acquia_cloud_client);

    $location = 'Local';
    if (array_key_exists('cloud', $key)) {
      $location = array_key_exists('local', $key) ? 'Local + Cloud' : 'Cloud';
    }
    $this->io->definitionList(
      ['SSH key property' => 'SSH key value'],
      new TableSeparator(),
      ['Location' => $location],
      ['Fingerprint (sha256)' => $key['fingerprint']],
      ['Fingerprint (md5)' => array_key_exists('cloud', $key) ? $key['cloud']['fingerprint'] : 'n/a'],
      ['UUID' => array_key_exists('cloud', $key) ? $key['cloud']['uuid'] : 'n/a'],
      ['Label' => array_key_exists('cloud', $key) ? $key['cloud']['label'] : $key['local']['filename']],
      ['Created at' => array_key_exists('cloud', $key) ? $key['cloud']['created_at'] : 'n/a'],
    );

    $this->io->writeln('Public key');
    $this->io->writeln('----------');
    $this->io->writeln($key['public_key']);

    return 0;
  }

  private function determineSshKey($acquia_cloud_client): array {
    $cloudKeysResponse = new SshKeys($acquia_cloud_client);
    $cloudKeys = (array) $cloudKeysResponse->getAll();
    $localKeys = $this->findLocalSshKeys();
    $keys = [];
    foreach ($cloudKeys as $key) {
      $fingerprint = FingerprintGenerator::getFingerprint($key->public_key, 'sha256');
      $keys[$fingerprint]['fingerprint'] = $fingerprint;
      $keys[$fingerprint]['public_key'] = $key->public_key;
      $keys[$fingerprint]['cloud'] = [
        'created_at' => $key->created_at,
        'fingerprint' => $key->fingerprint,
        'label' => $key->label,
        'uuid' => $key->uuid,
      ];
    }
    foreach ($localKeys as $key) {
      $fingerprint = FingerprintGenerator::getFingerprint($key->getContents(), 'sha256');
      $keys[$fingerprint]['fingerprint'] = $fingerprint;
      $keys[$fingerprint]['public_key'] = $key->getContents();
      $keys[$fingerprint]['local'] = [
        'filename' => $key->getFilename(),
      ];
    }
    if ($fingerprint = $this->input->getOption('fingerprint')) {
      return $keys[$fingerprint];
    }

    return $this->promptChooseFromObjectsOrArrays(
      $keys,
      'fingerprint',
      'fingerprint',
      'Choose an SSH key to view'
    );

  }

}
