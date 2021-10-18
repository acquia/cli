<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use React\EventLoop\Loop;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class WizardCommandBase.
 */
abstract class WizardCommandBase extends SshKeyCommandBase {

  /** @var string */
  protected $passphraseFilepath;

  /**
   * @var string
   */
  protected $privateSshKeyFilename;

  /**
   * @var string
   */
  protected $privateSshKeyFilepath;

  /**
   * @var string
   */
  protected $publicSshKeyFilepath;

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
    if ($this->commandRequiresAuthentication($input) && !$this::isMachineAuthenticated($this->datastoreCloud)) {
      $command_name = 'auth:login';
      $command = $this->getApplication()->find($command_name);
      $arguments = ['command' => $command_name];
      $create_input = new ArrayInput($arguments);
      $exit_code = $command->run($create_input, $output);
      if ($exit_code !== 0) {
        throw new AcquiaCliException("Unable to authenticate with the Cloud Platform.");
      }
    }

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
    $this->validateEnvironment();
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
   * @param string $private_ssh_key_filename
   */
  protected function setSshKeyFilepath(string $private_ssh_key_filename) {
    $this->privateSshKeyFilename = $private_ssh_key_filename;
    $this->privateSshKeyFilepath = $this->sshDir . '/' . $this->privateSshKeyFilename;
    $this->publicSshKeyFilepath = $this->privateSshKeyFilepath . '.pub';
    $this->passphraseFilepath = $this->localMachineHelper->getLocalFilepath('~/.passphrase');
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
   * Normalizes public SSH key by trimming and removing user and machine suffix.
   *
   * @param string $public_key
   *
   * @return string
   */
  protected function normalizePublicSshKey($public_key): string {
    $parts = explode('== ', $public_key);
    $key = $parts[0];

    return trim($key);
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
   * Polls the Cloud Platform until a successful SSH request is made to the dev
   * environment.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Exception
   */
  protected function pollAcquiaCloudUntilSshSuccess(
    OutputInterface $output
  ): void {
    // Create a loop to periodically poll the Cloud Platform.
    $loop = Loop::get();
    $spinner = LoopHelper::addSpinnerToLoop($loop, 'Waiting for the key to become available on the Cloud Platform', $output);

    // Wait for SSH key to be available on a web.
    $cloud_app_uuid = $this->determineCloudApplication(TRUE);
    $environment = $this->getAnyAhEnvironment($cloud_app_uuid);

    // Poll Cloud every 5 seconds.
    $loop->addPeriodicTimer(5, function () use ($output, $loop, $environment, $spinner) {
      try {
        $process = $this->sshHelper->executeCommand($environment, ['ls'], FALSE);
        if ($process->isSuccessful()) {
          LoopHelper::finishSpinner($spinner);
          $loop->stop();
          $output->writeln("\n<info>Your SSH key is ready for use!</info>\n");
        }
        else {
          $this->logger->debug($process->getOutput() . $process->getErrorOutput());
        }
      } catch (AcquiaCliException $exception) {
        // Do nothing. Keep waiting and looping and logging.
        $this->logger->debug($exception->getMessage());
      }
    });
    LoopHelper::addTimeoutToLoop($loop, 15, $spinner);
    $loop->run();
  }

  /**
   * Get the development environment for a given Cloud application.
   *
   * @param string $cloud_app_uuid
   *
   * @return \AcquiaCloudApi\Response\EnvironmentResponse|null
   * @throws \Exception
   */
  protected function getAnyAhEnvironment(string $cloud_app_uuid): ?EnvironmentResponse {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environment_resource = new Environments($acquia_cloud_client);
    $application_environments = iterator_to_array($environment_resource->getAll($cloud_app_uuid));
    $first_environment = reset($application_environments);
    return $first_environment;
  }

  /**
   * Asserts whether ANY SSH key has been added to the local keychain.
   *
   * @return bool
   * @throws \Exception
   */

  protected function sshKeyIsAddedToKeychain(): bool {
    $process = $this->localMachineHelper->execute([
      'ssh-add',
      '-L',
    ], NULL, NULL, FALSE);

    return strpos($process->getOutput(), $this->normalizePublicSshKey(file_get_contents($this->publicSshKeyFilepath))) !== FALSE;
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

  /**
   * Adds a given password protected local SSH key to the local keychain.
   *
   * @param string $filepath
   *   The filepath of the private SSH key.
   * @param string $password
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function addSshKeyToAgent(string $filepath, string $password): void {
    // We must use a separate script to mimic user input due to the limitations of the `ssh-add` command.
    // @see https://www.linux.com/topic/networking/manage-ssh-key-file-passphrase/
    $temp_filepath = $this->localMachineHelper->getFilesystem()
      ->tempnam(sys_get_temp_dir(), 'acli');
    $this->localMachineHelper->writeFile($temp_filepath, <<<'EOT'
#!/usr/bin/env bash
echo $SSH_PASS
EOT
    );
    $this->localMachineHelper->getFilesystem()->chmod($temp_filepath, 0755);

    $private_key_filepath = str_replace('.pub', '', $filepath);
    $process = $this->localMachineHelper->executeFromCmd('SSH_PASS=' . $password . ' DISPLAY=1 SSH_ASKPASS=' . $temp_filepath . ' ssh-add ' . $private_key_filepath, NULL, NULL, FALSE);
    $this->localMachineHelper->getFilesystem()->remove($temp_filepath);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to add the SSH key to local SSH agent:' . $process->getOutput() . $process->getErrorOutput());
    }
  }

}
