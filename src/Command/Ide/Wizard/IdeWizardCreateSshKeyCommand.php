<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use React\EventLoop\Factory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardCreateSshKeyCommand.
 */
class IdeWizardCreateSshKeyCommand extends IdeWizardCommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ide:wizard:ssh-key:create-upload')
      ->setDescription('Wizard to perform first time setup tasks within an IDE')
      ->setHidden(!CommandBase::isAcquiaRemoteIde());
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
    $this->requireRemoteIdeEnvironment();
    $checklist = new Checklist($output);

    // Create local SSH key.
    if (!$this->localIdeSshKeyExists()) {
      // Just in case the public key exists and the private doesn't, remove the public key.
      $this->deleteLocalIdeSshKey();
      // Just in case there's an orphaned key on Acquia Cloud for this Remote IDE.
      $this->deleteIdeSshKeyFromCloud();

      $checklist->addItem('Creating a local SSH key');

      // Create SSH key.
      $password = md5(random_bytes(10));
      $this->savePassPhraseToFile($password);
      $this->createLocalSshKey($this->privateSshKeyFilename, $password);

      $checklist->completePreviousItem();
    }
    else {
      $checklist->addItem('Already created a local key');
      $checklist->completePreviousItem();
    }

    // Upload SSH key to Acquia Cloud.
    if (!$this->userHasUploadedIdeKeyToCloud()) {
      $checklist->addItem('Uploading local key to Acquia Cloud');

      // Just in case there is an uploaded key but it doesn't actually match the local key, delete remote key!
      $this->deleteIdeSshKeyFromCloud();
      $this->uploadSshKeyToCloud($this->ide, $this->publicSshKeyFilepath);

      $checklist->completePreviousItem();
    }
    else {
      $checklist->addItem('Already uploaded local key to Acquia Cloud');
      $checklist->completePreviousItem();
    }

    // Add SSH key to local keychain.
    if (!$this->sshKeyIsAddedToKeychain()) {
      $checklist->addItem('Adding SSH key to local keychain');
      $this->addSshKeyToAgent($this->publicSshKeyFilepath, $this->getPassPhraseFromFile());
      $checklist->completePreviousItem();
    }
    else {
      $checklist->addItem('Already added SSH key to local keychain');
      $checklist->completePreviousItem();
    }

    // Wait for the key to register on Acquia Cloud.
    if (!$this->userHasUploadedIdeKeyToCloud()) {
      $this->pollAcquiaCloudUntilSshSuccess($output);
    }

    return 0;

    // Tests:
    // Delete from cloud.
    // Delete private key.
    // Delete public key.
    // Delete from keychain.
    // Delete local passphrase file.
    // Assert added to keychain.
    // Assert both keys exist.
    // Assert uploaded to cloud.
    //
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
  protected function addSshKeyToAgent($filepath, $password): void {
    // We must use a separate script to mimic user input due to the limitations of the `ssh-add` command.
    $passphrase_prompt_script = __DIR__ . '/passphrase_prompt.sh';
    $private_key_filepath = str_replace('.pub', '', $filepath);
    $process = $this->getApplication()->getLocalMachineHelper()->executeFromCmd('SSH_PASS=' . $password . ' DISPLAY=1 SSH_ASKPASS=' . $passphrase_prompt_script . ' ssh-add ' . $private_key_filepath . ' < /dev/null', NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to add SSH key to local SSH agent:' . $process->getOutput() . $process->getErrorOutput());
    }
  }

  /**
   * Asserts whether ANY SSH key has been added to the local keychain.
   *
   * @return bool
   */
  protected function sshKeyIsAddedToKeychain(): bool {
    $process = $this->getApplication()->getLocalMachineHelper()->execute([
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
   * @return false|string|null
   */
  protected function getPassPhraseFromFile() {
    if (file_exists($this->passphraseFilepath)) {
      return file_get_contents($this->passphraseFilepath);
    }

    return NULL;
  }

  /**
   * Assert whether ANY local key exists that has a corresponding key on Acquia Cloud.
   *
   * @return bool
   */
  protected function userHasUploadedIdeKeyToCloud(): bool {
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
      foreach ($cloud_keys as $index => $cloud_key) {
        if (
          $cloud_key->label === $this->getIdeSshKeyLabel($this->ide)
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
   */
  protected function getDevEnvironment($cloud_app_uuid): ?EnvironmentResponse {
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $environment_resource = new Environments($acquia_cloud_client);
    $application_environments = iterator_to_array($environment_resource->getAll($cloud_app_uuid));
    foreach ($application_environments as $environment) {
      if ($environment->name === 'dev') {
        return $environment;
      }
    }
    return NULL;
  }

  /**
   * Polls Acquia Cloud until a successful SSH request is made to the dev environment.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function pollAcquiaCloudUntilSshSuccess(
    OutputInterface $output
  ): void {
    // Create a loop to periodically poll Acquia Cloud.
    $loop = Factory::create();
    $spinner = LoopHelper::addSpinnerToLoop($loop, 'Waiting for key to become available on Acquia Cloud web servers...', $output);

    // Wait for SSH key to be available on a web.
    $cloud_app_uuid = $this->determineCloudApplication(TRUE);
    $environment = $this->getDevEnvironment($cloud_app_uuid);

    // Poll Cloud every 5 seconds.
    $loop->addPeriodicTimer(5, function () use ($output, $loop, $environment, $spinner) {
      try {
        $process = $this->getApplication()->getSshHelper()->executeCommand($environment, ['ls'], FALSE);
        if ($process->isSuccessful()) {
          LoopHelper::finishSpinner($spinner);
          $output->writeln("\n<info>Your SSH key is ready for use.</info>");
          $loop->stop();
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
    LoopHelper::addTimeoutToLoop($loop, 10, $spinner, $output);
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
  protected function createLocalSshKey(string $private_ssh_key_filename, string $password): int {
    $command = $this->getApplication()->find('ssh-key:create');
    $arguments = [
      'command' => $command->getName(),
      '--filename' => $private_ssh_key_filename,
      '--password' => $password,
    ];
    $create_input = new ArrayInput($arguments);
    $return_code = $command->run($create_input, $this->output);
    if ($return_code !== 0) {
      throw new AcquiaCliException('Unable to generate a local SSH key.');
    }
    return $return_code;
  }

  /**
   * @param string $public_ssh_key_filepath
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function uploadSshKeyToCloud($ide, string $public_ssh_key_filepath): void {
    $command = $this->getApplication()->find('ssh-key:upload');
    $arguments = [
      'command' => $command->getName(),
      '--label' => $this->getIdeSshKeyLabel($ide),
      '--filepath' => $public_ssh_key_filepath,
      '--no-wait' => '',
    ];
    $upload_input = new ArrayInput($arguments);
    $returnCode = $command->run($upload_input, new NullOutput());
    if ($returnCode !== 0) {
      throw new AcquiaCliException('Unable to upload SSH key to Acquia Cloud');
    }
  }

  protected function deleteIdeSshKeyFromCloud(): void {
    if ($cloud_key = $this->findIdeSshKeyOnCloud()) {
      $this->deleteSshKeyFromCloud($cloud_key);
    }
  }

}
