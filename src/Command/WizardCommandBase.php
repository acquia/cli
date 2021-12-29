<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class WizardCommandBase.
 */
abstract class WizardCommandBase extends SshKeyCommandBase {

  /**
   * @var \Acquia\Cli\Output\Checklist
   */
  private $checklist;

  abstract protected function deleteThisSshKeyFromCloud();

  abstract protected function getSshKeyLabel();

  abstract protected function validateEnvironment();

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return void
   * @throws \Acquia\Cli\Exception\AcquiaCliException|\Psr\Cache\InvalidArgumentException
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    if ($this->commandRequiresAuthentication($input) && !$this::isMachineAuthenticated($this->datastoreCloud, $this->cloudApiClientService)) {
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

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->checklist = new Checklist($output);
    $key_was_uploaded = FALSE;

    // Create local SSH key.
    if (!$this->localSshKeyExists() || !$this->passPhraseFileExists()) {
      // Just in case the public key exists and the private doesn't, remove the public key.
      $this->deleteLocalSshKey();
      // Just in case there's an orphaned key on the Cloud Platform for this Cloud IDE.
      $this->deleteThisSshKeyFromCloud();

      $this->checklist->addItem('Creating a local SSH key');

      // Create SSH key.
      $password = md5(random_bytes(10));
      $this->savePassPhraseToFile($password);
      $this->createLocalSshKey($this->privateSshKeyFilename, $password);

      $this->checklist->completePreviousItem();
      $key_was_uploaded = TRUE;
    }
    else {
      $this->checklist->addItem('Already created a local key');
      $this->checklist->completePreviousItem();
    }

    // Upload SSH key to the Cloud Platform.
    if (!$this->userHasUploadedThisKeyToCloud($this->getSshKeyLabel())) {
      $this->checklist->addItem('Uploading the local key to the Cloud Platform');

      // Just in case there is an uploaded key but it doesn't actually match the local key, delete remote key!
      $this->deleteThisSshKeyFromCloud();
      $this->uploadSshKeyToCloud($this->getSshKeyLabel(), $this->publicSshKeyFilepath);

      $this->checklist->completePreviousItem();
      $key_was_uploaded = TRUE;
    }
    else {
      $this->checklist->addItem('Already uploaded the local key to the Cloud Platform');
      $this->checklist->completePreviousItem();
    }

    // Add SSH key to local keychain.
    if (!$this->sshKeyIsAddedToKeychain()) {
      $this->checklist->addItem('Adding the SSH key to local keychain');
      $this->addSshKeyToAgent($this->publicSshKeyFilepath, $this->getPassPhraseFromFile());
    }
    else {
      $this->checklist->addItem('Already added the SSH key to local keychain');
    }
    $this->checklist->completePreviousItem();

    // Wait for the key to register on the Cloud Platform.
    if ($key_was_uploaded) {
      $this->pollAcquiaCloudUntilSshSuccess($output);
    }

    return 0;
  }

  /**
   *
   */
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
   * @return bool|int
   */
  protected function savePassPhraseToFile(string $passphrase) {
    return file_put_contents($this->passphraseFilepath, $passphrase);
  }

  /**
   * @return string
   */
  protected function getPassPhraseFromFile(): string {
    return file_get_contents($this->passphraseFilepath);
  }

  /**
   * Create a local SSH key via the `ssh-key:create` command.
   *
   * @param string $private_ssh_key_filename
   * @param string $password
   *
   * @return int
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function createLocalSshKey(string $private_ssh_key_filename, string $password): int {
    $return_code = $this->executeAcliCommand('ssh-key:create', [
      '--filename' => $private_ssh_key_filename,
      '--password' => $password,
    ]);
    if ($return_code !== 0) {
      throw new AcquiaCliException('Unable to generate a local SSH key.');
    }
    return $return_code;
  }

  /**
   * @param string $label
   * @param string $public_ssh_key_filepath
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function uploadSshKeyToCloud(string $label, string $public_ssh_key_filepath): void {
    $return_code = $this->executeAcliCommand('ssh-key:upload', [
      '--label' => $label,
      '--filepath' => $public_ssh_key_filepath,
      '--no-wait' => '',
    ]);
    if ($return_code !== 0) {
      throw new AcquiaCliException('Unable to upload the SSH key to the Cloud Platform');
    }
  }

  /**
   * Assert whether ANY local key exists that has a corresponding key on the
   * Cloud Platform.
   *
   * @param string $label
   *
   * @return bool
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

  /**
   * @return bool
   */
  protected function passPhraseFileExists() {
    return file_exists($this->passphraseFilepath);
  }

  /**
   * @return bool
   */
  protected function localSshKeyExists(): bool {
    return file_exists($this->publicSshKeyFilepath) && file_exists($this->privateSshKeyFilepath);
  }

}
