<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\Client;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zumba\Amplitude\Amplitude;

trait SshCommandTrait {

  private function deleteSshKeyFromCloud($output, $cloud_key = NULL): int {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    if (!$cloud_key) {
      $cloud_key = $this->determineCloudKey($acquia_cloud_client);
    }

    $response = $acquia_cloud_client->makeRequest('delete', '/account/ssh-keys/' . $cloud_key->uuid);
    if ($response->getStatusCode() === 202) {
      $output->writeln("<info>Successfully deleted SSH key <options=bold>$cloud_key->label</> from the Cloud Platform.</info>");
      $local_keys = $this->findLocalSshKeys();
      foreach ($local_keys as $local_file) {
        if (trim($local_file->getContents()) === trim($cloud_key->public_key)) {
          $private_key_path = str_replace('.pub', '', $local_file->getRealPath());
          $public_key_path = $local_file->getRealPath();
          $answer = $this->io->confirm("Do you also want to delete the corresponding local key files {$local_file->getRealPath()} and $private_key_path ?", FALSE);
          if ($answer) {
            $this->localMachineHelper->getFilesystem()->remove([
              $local_file->getRealPath(),
              $private_key_path,
            ]);
            $this->io->success("Deleted $public_key_path and $private_key_path");
            return 0;
          }
        }
      }
      return 0;
    }

    throw new AcquiaCliException($response->getBody()->getContents());
  }

  private function determineCloudKey(Client $acquia_cloud_client): object|array|null {
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    return $this->promptChooseFromObjectsOrArrays(
      $cloud_keys,
      'uuid',
      'label',
      'Choose an SSH key to delete from the Cloud Platform'
    );
  }

  /**
   * @return \Symfony\Component\Finder\SplFileInfo[]
   */
  protected function findLocalSshKeys(): array {
    $finder = $this->localMachineHelper->getFinder();
    $finder->files()->in($this->sshDir)->name('*.pub')->ignoreUnreadableDirs();
    return iterator_to_array($finder);
  }

  protected function promptWaitForSsh(SymfonyStyle $io): bool {
    $io->note("It may take an hour or more before the SSH key is installed on all of your application's servers. Create a Support ticket for further assistance.");
    $wait = $io->confirm("Would you like to wait until your key is installed on all of your application's servers?");
    Amplitude::getInstance()->queueEvent('User waited for SSH key upload', ['wait' => $wait]);
    return $wait;
  }

}
