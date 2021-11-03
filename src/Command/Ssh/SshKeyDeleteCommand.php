<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use violuke\RsaSshKeyFingerprint\FingerprintGenerator;

/**
 * Class SshKeyDeleteCommand.
 */
class SshKeyDeleteCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:delete';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Delete an SSH key')
      ->addOption('cloud-key-uuid', 'uuid', InputOption::VALUE_REQUIRED);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_key = $this->determineCloudKey($acquia_cloud_client);

    $response = $acquia_cloud_client->makeRequest('delete', '/account/ssh-keys/' . $cloud_key->uuid);
    if ($response->getStatusCode() === 202) {
      $output->writeln("<info>Successfully deleted SSH key <options=bold>{$cloud_key->label}</> from the Cloud Platform.</info>");
      $local_keys = $this->findLocalSshKeys();
      foreach ($local_keys as $local_file) {
        if (trim($local_file->getContents()) === trim($cloud_key->public_key)) {
          $private_key_path = str_replace('.pub', '', $local_file->getRealPath());
          $answer = $this->io->confirm("Do you also want to delete the corresponding local key files {$local_file->getRealPath()} and $private_key_path ?", FALSE);
          if ($answer) {
            $this->localMachineHelper->getFilesystem()->remove([
              $local_file->getRealPath(),
              $private_key_path,
            ]);
            $this->io->success("Deleted {$local_file->getRealPath()} and $private_key_path");
            return 0;
          }
        }
      }
      return 0;
    }

    throw new AcquiaCliException($response->getBody()->getContents());
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   *
   * @return array|object|null
   */
  protected function determineCloudKey($acquia_cloud_client) {
    if ($this->input->getOption('cloud-key-uuid')) {
      $cloud_key_uuid = self::validateUuid($this->input->getOption('cloud-key-uuid'));
      $cloud_key = $acquia_cloud_client->request('get', '/account/ssh-keys/' . $cloud_key_uuid);
      return $cloud_key;
    }

    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $cloud_key = $this->promptChooseFromObjectsOrArrays(
      $cloud_keys,
      'uuid',
      'label',
      'Choose an SSH key to delete from the Cloud Platform'
    );

    return $cloud_key;
  }

}
