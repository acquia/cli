<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\IdeResponse;
use React\EventLoop\Factory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardCreateSshKeyCommand.
 */
class IdeWizardCreateSshKeyCommand extends IdeWizardCommandBase {

  protected static $defaultName = 'ide:wizard:ssh-key:create-upload';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Wizard to perform first time setup tasks within an IDE')
      ->setAliases(['ide:wizard'])
      ->setHidden(!CommandBase::isAcquiaCloudIde());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int|void
   * @throws \Acquia\Cli\Exception\AcquiaCliException
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
    $this->requireCloudIdeEnvironment();
    $this->checklist = new Checklist($output);
    $key_was_uploaded = FALSE;

    // Create local SSH key.
    if (!$this->localIdeSshKeyExists()) {
      // Just in case the public key exists and the private doesn't, remove the public key.
      $this->deleteLocalIdeSshKey();
      // Just in case there's an orphaned key on the Cloud Platform for this Cloud IDE.
      $this->deleteIdeSshKeyFromCloud();

      $this->checklist->addItem('Creating a local SSH key');

      // Create SSH key.
      $this->createLocalSshKey($this->privateSshKeyFilename);

      $this->checklist->completePreviousItem();
      $key_was_uploaded = TRUE;
    }
    else {
      $this->checklist->addItem('Already created a local key');
      $this->checklist->completePreviousItem();
    }

    // Upload SSH key to the Cloud Platform.
    if (!$this->userHasUploadedIdeKeyToCloud()) {
      $this->checklist->addItem('Uploading the local key to the Cloud Platform');

      // Just in case there is an uploaded key but it doesn't actually match the local key, delete remote key!
      $this->deleteIdeSshKeyFromCloud();
      $this->uploadSshKeyToCloud($this->ide, $this->publicSshKeyFilepath);

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
      $this->addSshKeyToAgent($this->publicSshKeyFilepath);
      $this->checklist->completePreviousItem();
    }
    else {
      $this->checklist->addItem('Already added the SSH key to local keychain');
      $this->checklist->completePreviousItem();
    }

    // Wait for the key to register on the Cloud Platform.
    if ($key_was_uploaded) {
      $this->pollAcquiaCloudUntilSshSuccess($output);
    }

    return 0;
  }

  protected function passPhraseFileExists() {
    return file_exists($this->passphraseFilepath);
  }

  protected function localIdeSshKeyExists(): bool {
    return file_exists($this->publicSshKeyFilepath) && file_exists($this->privateSshKeyFilepath);
  }

  /**
   * Adds a given password protected local SSH key to the local keychain.
   *
   * @param $filepath
   *   The filepath of the private SSH key.
   * @param $password
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function addSshKeyToAgent($filepath,$password = ""): void {
    // We must use a separate script to mimic user input due to the limitations of the `ssh-add` command.
    // @see https://www.linux.com/topic/networking/manage-ssh-key-file-passphrase/
    $temp_filepath = $this->localMachineHelper->getFilesystem()->tempnam(sys_get_temp_dir(), 'acli');

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
   * Normalizes public SSH key by trimming and removing user and machine suffix.
   *
   * @param $public_key
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
  protected function savePassPhraseToFile($passphrase) {
    return file_put_contents($this->passphraseFilepath, $passphrase);
  }

  /**
   * @return string
   */
  protected function getPassPhraseFromFile(): string {
    return file_get_contents($this->passphraseFilepath);
  }

  /**
   * Assert whether ANY local key exists that has a corresponding key on the Cloud Platform.
   *
   * @return bool
   * @throws \Exception
   */
  protected function userHasUploadedIdeKeyToCloud(): bool {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    foreach ($cloud_keys as $index => $cloud_key) {
      if (
        $cloud_key->label === $this::getIdeSshKeyLabel($this->ide)
        // Assert that a corresponding local key exists.
        && $this->localIdeSshKeyExists()
        // Assert local public key contents match Cloud public key contents.
        && $this->normalizePublicSshKey($cloud_key->public_key) === $this->normalizePublicSshKey(file_get_contents($this->publicSshKeyFilepath))
      ) {
        return TRUE;
      }
    }
    return FALSE;
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
   * Polls the Cloud Platform until a successful SSH request is made to the dev environment.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Exception
   */
  protected function pollAcquiaCloudUntilSshSuccess(
    OutputInterface $output
  ): void {
    // Create a loop to periodically poll the Cloud Platform.
    $loop = Factory::create();
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
      }
      catch (AcquiaCliException $exception) {
        // Do nothing. Keep waiting and looping and logging.
        $this->logger->debug($exception->getMessage());
      }
    });
    LoopHelper::addTimeoutToLoop($loop, 15, $spinner, $output);
    $loop->run();
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
  protected function createLocalSshKey(string $private_ssh_key_filename, string $password = ""): int {
    $return_code = $this->executeAcliCommand('ssh-key:create', [
       '--filename' => $private_ssh_key_filename,
       '--password' => $password,
       '--is-wizard' => TRUE,
     ]);
    if ($return_code !== 0) {
      throw new AcquiaCliException('Unable to generate a local SSH key.');
    }
    return $return_code;
  }

  /**
   * @param $ide
   * @param string $public_ssh_key_filepath
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function uploadSshKeyToCloud(IdeResponse $ide, string $public_ssh_key_filepath): void {
    $return_code = $this->executeAcliCommand('ssh-key:upload', [
      '--label' => $this::getIdeSshKeyLabel($ide),
      '--filepath' => $public_ssh_key_filepath,
      '--no-wait' => '',
    ]);
    if ($return_code !== 0) {
      throw new AcquiaCliException('Unable to upload the SSH key to the Cloud Platform');
    }
  }

  protected function deleteIdeSshKeyFromCloud(): void {
    if ($cloud_key = $this->findIdeSshKeyOnCloud($this::getThisCloudIdeUuid())) {
      $this->deleteSshKeyFromCloud($cloud_key);
    }
  }

}
