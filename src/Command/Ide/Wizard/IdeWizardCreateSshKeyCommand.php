<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Ides;
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

  /** @var string */
  protected $passphraseFilepath;

  /**
   * Initializes the command just after the input has been validated.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   An InputInterface instance.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   An OutputInterface instance.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->passphraseFilepath = $this->getApplication()->getLocalMachineHelper()->getLocalFilepath('~/.passphrase');
  }

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

    $ide_uuid = CommandBase::getThisRemoteIdeUuid();

    // @todo Delete and recreate in this instance.
    if ($this->userHasUploadedLocalKeyToCloud()) {
      $output->writeln("<info>You have already uploaded a local key to Acquia Cloud.</info>");
      return 0;
    }

    $checklist = new Checklist($output);
    $checklist->addItem('Creating a local SSH key');

    // Create SSH key.
    $filename = $this->getSshKeyFilename($ide_uuid);
    $password = md5(random_bytes(10));
    $this->savePassPhraseToFile($password);

    $command = $this->getApplication()->find('ssh-key:create');
    $arguments = [
      'command' => $command->getName(),
      '--filename'  => $filename,
      '--password'  => $password,
    ];
    $create_input = new ArrayInput($arguments);
    $returnCode = $command->run($create_input, $output);
    if ($returnCode !== 0) {
      throw new AcquiaCliException('Unable to generate a local SSH key.');
    }
    $checklist->completePreviousItem();

    // Upload SSH key.
    $checklist->addItem('Uploading local key to Acquia Cloud');
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $ides_resource = new Ides($acquia_cloud_client);
    $ide = $ides_resource->get($ide_uuid);
    $ssh_key_label = $this->getIdeSshKeyLabel($ide);
    $public_key_filepath = $this->getApplication()->getSshKeysDir() . '/' . $filename . '.pub';
    $command = $this->getApplication()->find('ssh-key:upload');
    $arguments = [
      'command' => $command->getName(),
      '--label'  => $ssh_key_label,
      '--filepath' => $public_key_filepath,
      '--no-wait' => '',
    ];
    $upload_input = new ArrayInput($arguments);
    $returnCode = $command->run($upload_input, new NullOutput());
    if ($returnCode !== 0) {
      throw new AcquiaCliException('Unable to upload SSH key to Acquia Cloud');
    }
    $checklist->completePreviousItem();

    $checklist->addItem('Adding SSH key to local keychain');
    $this->addSshKeyToAgent($public_key_filepath, $password);
    $checklist->completePreviousItem();

    // Wait for the key to register on Acquia Cloud.
    $this->pollAcquiaCloud($output);

    return 0;
  }

  /**
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function addSshKeyToAgent($filepath, $password): string {
    if (!$this->sshKeyIsAddedToKeychain()) {
      $passphrase_prompt_script = __DIR__ . '/passphrase_prompt.sh';
      $private_key_filepath = str_replace('.pub', '', $filepath);
      $process = $this->getApplication()->getLocalMachineHelper()->executeFromCmd('SSH_PASS=' . $password . ' DISPLAY=1 SSH_ASKPASS=' . $passphrase_prompt_script . ' ssh-add ' . $private_key_filepath . ' < /dev/null', NULL, NULL, FALSE);
    }
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to add SSH key to local SSH agent:' . $process->getOutput() . $process->getErrorOutput());
    }

    return $filepath;
  }

  /**
   * @return bool
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function sshKeyIsAddedToKeychain() {
    $process = $this->getApplication()->getLocalMachineHelper()->execute([
      'ssh-add',
      '-L',
    ], NULL, NULL, FALSE);

    return $process->getOutput() == 'The agent has no identities';
  }

  /**
   * @param string $passphrase
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
   * @return bool
   */
  protected function userHasUploadedLocalKeyToCloud(): bool {
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $local_keys = $this->findLocalSshKeys();
    foreach ($local_keys as $local_index => $local_file) {
      foreach ($cloud_keys as $index => $cloud_key) {
        if (
          // Assert local public key contents match Cloud public key contents.
          trim($local_file->getContents()) === trim($cloud_key->public_key)
          // Assert that a corresponding private key exists.
          && file_exists(str_replace('.pub', '', $local_file->getRealPath()))
        ) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
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
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function pollAcquiaCloud(
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
      }
      catch (AcquiaCliException $exception) {
        // Do nothing. Keep waiting and looping.
      }
    });
    LoopHelper::addTimeoutToLoop($loop, 10, $spinner, $output);
    $loop->run();
  }

}
