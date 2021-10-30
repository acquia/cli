<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\IdeResponse;
use React\EventLoop\Loop;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SshKeyCommandBase.
 */
abstract class SshKeyCommandBase extends CommandBase {

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
   * @param string $private_ssh_key_filename
   */
  protected function setSshKeyFilepath(string $private_ssh_key_filename) {
    $this->privateSshKeyFilename = $private_ssh_key_filename;
    $this->privateSshKeyFilepath = $this->sshDir . '/' . $this->privateSshKeyFilename;
    $this->publicSshKeyFilepath = $this->privateSshKeyFilepath . '.pub';
    $this->passphraseFilepath = $this->localMachineHelper->getLocalFilepath('~/.passphrase');
  }

  /**
   * @return \Symfony\Component\Finder\SplFileInfo[]
   * @throws \Exception
   */
  protected function findLocalSshKeys(): array {
    $finder = $this->localMachineHelper->getFinder();
    $finder->files()->in($this->sshDir)->name('*.pub')->ignoreUnreadableDirs();
    return iterator_to_array($finder);
  }

  /**
   *
   * @param \AcquiaCloudApi\Response\IdeResponse $ide
   *
   * @return string
   */
  public static function getIdeSshKeyLabel(IdeResponse $ide): string {
    return self::normalizeSshKeyLabel('IDE_' . $ide->label . '_' . $ide->uuid);
  }

  /**
   * @param string $label
   *   The label to normalize.
   *
   * @return string|string[]|null
   */
  public static function normalizeSshKeyLabel($label) {
    // It may only contain letters, numbers and underscores.
    return preg_replace('/[^A-Za-z0-9_]/', '', $label);
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

    $key_contents = $this->normalizePublicSshKey($this->localMachineHelper->readFile($this->publicSshKeyFilepath));
    return strpos($process->getOutput(), $key_contents) !== FALSE;
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
    // We could actually use _any_ application here.
    $cloud_app_uuid = $this->getAnyAhApplication();
    $environment = $this->getAnyAhEnvironment($cloud_app_uuid);

    // Poll Cloud every 5 seconds.
    $loop->addPeriodicTimer(5, function () use ($output, $loop, $environment, $spinner) {
      try {
        // @todo  Mock this! Return successfully.
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
   * Get the first Cloud application available.
   *
   * @return false|mixed|string|null
   * @throws \Exception
   */
  protected function getAnyAhApplication() {
    if ($app_uuid = $this->determineCloudApplication()) {
      return $app_uuid;
    }
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $applications_resource = new Applications($acquia_cloud_client);
    $applications = iterator_to_array($applications_resource->getAll());
    $first_application = reset($applications);

    return $first_application->uuid;
  }

  /**
   * Get the first environment for a given Cloud application.
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

}
