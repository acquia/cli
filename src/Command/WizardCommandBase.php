<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class WizardCommandBase.
 */
abstract class WizardCommandBase extends SshKeyCommandBase {

  abstract protected function validateEnvironment(): void;

  /**
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException|\Psr\Cache\InvalidArgumentException
   * @throws \Symfony\Component\Console\Exception\ExceptionInterface
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function initialize(InputInterface $input, OutputInterface $output): void {
    if ($this->commandRequiresAuthentication() && !$this->cloudApiClientService->isMachineAuthenticated()) {
      $command_name = 'auth:login';
      $command = $this->getApplication()->find($command_name);
      $arguments = ['command' => $command_name];
      $create_input = new ArrayInput($arguments);
      $exit_code = $command->run($create_input, $output);
      if ($exit_code !== 0) {
        throw new AcquiaCliException("Unable to authenticate with the Cloud Platform.");
      }
    }
    $this->validateEnvironment();

    parent::initialize($input, $output);
  }

  protected function deleteLocalSshKey(): void {
    $this->localMachineHelper->getFilesystem()->remove([
      $this->publicSshKeyFilepath,
      $this->privateSshKeyFilepath,
    ]);
  }

  /**
   * @param string $passphrase
   *   The passphrase.
   *
   */
  protected function savePassPhraseToFile(string $passphrase): bool|int {
    return file_put_contents($this->passphraseFilepath, $passphrase);
  }

  protected function getPassPhraseFromFile(): string {
    return file_get_contents($this->passphraseFilepath);
  }

  /**
   * Assert whether ANY local key exists that has a corresponding key on the
   * Cloud Platform.
   *
   *
   */
  protected function userHasUploadedThisKeyToCloud(string $label): bool {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    foreach ($cloud_keys as $index => $cloud_key) {
      if (
        $cloud_key->label === $label
        // Assert that a corresponding local key exists.
        && $this->localSshKeyExists()
        // Assert local public key contents match Cloud public key contents.
        && $this->normalizePublicSshKey($cloud_key->public_key) === $this->normalizePublicSshKey(file_get_contents($this->publicSshKeyFilepath))
      ) {
        return TRUE;
      }
    }
    return FALSE;
  }

  protected function passPhraseFileExists(): bool {
    return file_exists($this->passphraseFilepath);
  }

  protected function localSshKeyExists(): bool {
    return file_exists($this->publicSshKeyFilepath) && file_exists($this->privateSshKeyFilepath);
  }

}
