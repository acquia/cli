<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshCommandTrait;
use Acquia\Cli\Output\Spinner\Spinner;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\SshKeys;
use AcquiaCloudApi\Response\IdeResponse;
use Closure;
use React\EventLoop\Loop;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;
use Zumba\Amplitude\Amplitude;

abstract class SshKeyCommandBase extends CommandBase {

  use SshCommandTrait;

  protected string $passphraseFilepath;

  protected string $privateSshKeyFilename;

  protected string $privateSshKeyFilepath;

  protected string $publicSshKeyFilepath;

  protected function setSshKeyFilepath(string $private_ssh_key_filename): void {
    $this->privateSshKeyFilename = $private_ssh_key_filename;
    $this->privateSshKeyFilepath = $this->sshDir . '/' . $this->privateSshKeyFilename;
    $this->publicSshKeyFilepath = $this->privateSshKeyFilepath . '.pub';
  }

  public static function getIdeSshKeyLabel(IdeResponse $ide): string {
    return self::normalizeSshKeyLabel('IDE_' . $ide->label . '_' . $ide->uuid);
  }

  /**
   * @param string|null $label
   *   The label to normalize.
   * @return string|null
   */
  public static function normalizeSshKeyLabel(?string $label): string|null {
    if (is_null($label)) {
      throw new RuntimeException('The label cannot be empty');
    }
    // It may only contain letters, numbers and underscores.
    return preg_replace('/[^A-Za-z0-9_]/', '', $label);
  }

  /**
   * Normalizes public SSH key by trimming and removing user and machine suffix.
   */
  protected function normalizePublicSshKey(string $public_key): string {
    $parts = explode('== ', $public_key);
    $key = $parts[0];

    return trim($key);
  }

  /**
   * Asserts whether ANY SSH key has been added to the local keychain.
   */
  protected function sshKeyIsAddedToKeychain(): bool {
    $process = $this->localMachineHelper->execute([
      'ssh-add',
      '-L',
    ], NULL, NULL, FALSE);

    if ($process->isSuccessful()) {
      $key_contents = $this->normalizePublicSshKey($this->localMachineHelper->readFile($this->publicSshKeyFilepath));
      return str_contains($process->getOutput(), $key_contents);
    }
    return FALSE;
  }

  /**
   * Adds a given password protected local SSH key to the local keychain.
   *
   * @param string $filepath
   *   The filepath of the private SSH key.
   */
  protected function addSshKeyToAgent(string $filepath, string $password): void {
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
   * Polls the Cloud Platform until a successful SSH request is made to the dev
   * environment.
   *
   * @infection-ignore-all
   */
  protected function pollAcquiaCloudUntilSshSuccess(
    OutputInterface $output
  ): void {
    // Create a loop to periodically poll the Cloud Platform.
    $loop = Loop::get();
    $timers = [];
    $startTime = time();
    $cloud_app_uuid = $this->determineCloudApplication(TRUE);
    $permissions = $this->cloudApiClientService->getClient()->request('get', "/applications/{$cloud_app_uuid}/permissions");
    $perms = array_column($permissions, 'name');
    $mappings = $this->checkPermissions($perms, $cloud_app_uuid, $output);
    foreach ($mappings as $env_name => $config) {
      $spinner = new Spinner($output, 4);
      $spinner->setMessage("Waiting for the key to become available in Cloud Platform $env_name environments");
      $spinner->start();
      $mappings[$env_name]['timer'] = $loop->addPeriodicTimer($spinner->interval(),
        static function () use ($spinner): void {
          $spinner->advance();
        });
      $mappings[$env_name]['spinner'] = $spinner;
    }
    $callback = function () use ($output, $loop, &$mappings, &$timers, $startTime): void {
      foreach ($mappings as $env_name => $config) {
        try {
          $process = $this->sshHelper->executeCommand($config['ssh_target'], ['ls'], FALSE);
          if (($process->getExitCode() === 128 && $env_name === 'git') || $process->isSuccessful()) {
            // SSH key is available on this host, but may be pending on others.
            $config['spinner']->finish();
            $loop->cancelTimer($config['timer']);
            unset($mappings[$env_name]);
          }
          else {
            // SSH key isn't available on this host... yet.
            $this->logger->debug($process->getOutput() . $process->getErrorOutput());
          }
        }
        catch (AcquiaCliException $exception) {
          $this->logger->debug($exception->getMessage());
        }
      }
      if (empty($mappings)) {
        // SSH key is available on every host.
        Amplitude::getInstance()->queueEvent('SSH key upload', ['result' => 'success', 'duration' => time() - $startTime]);
        $output->writeln("\n<info>Your SSH key is ready for use!</info>\n");
        foreach ($timers as $timer) {
          $loop->cancelTimer($timer);
        }
        $timers = [];
      }
    };
    // Poll Cloud every 5 seconds.
    $timers[] = $loop->addPeriodicTimer(5, $callback);
    $timers[] = $loop->addTimer(0.1, $callback);
    $timers[] = $loop->addTimer(60 * 60, function () use ($output, $loop, &$timers): void {
      // Upload timed out.
      $output->writeln("\n<comment>This is taking longer than usual. It will happen eventually!</comment>\n");
      Amplitude::getInstance()->queueEvent('SSH key upload', ['result' => 'timeout']);
      foreach ($timers as $timer) {
        $loop->cancelTimer($timer);
      }
      $timers = [];
    });
    $loop->run();
  }

  private function checkPermissions(array $perms, string $cloud_app_uuid, OutputInterface $output): array {
    $mappings = [];
    $needed_perms = ['add ssh key to git', 'add ssh key to non-prod', 'add ssh key to prod'];
    foreach ($needed_perms as $index => $perm) {
      if (in_array($perm, $perms, TRUE)) {
        switch ($perm) {
          case 'add ssh key to git':
            $full_url = $this->getAnyVcsUrl($cloud_app_uuid);
            $url_parts = explode(':', $full_url);
            $mappings['git']['ssh_target'] = $url_parts[0];
            break;
          case 'add ssh key to non-prod':
            $mappings['nonprod']['ssh_target'] = $this->getAnyNonProdAhEnvironment($cloud_app_uuid);
            break;
          case 'add ssh key to prod':
            $mappings['prod']['ssh_target'] = $this->getAnyProdAhEnvironment($cloud_app_uuid);
            break;
        }
        unset($needed_perms[$index]);
      }
    }
    if (!empty($needed_perms)) {
      $perm_string = implode(", ", $needed_perms);
      $output->writeln('<comment>You do not have access to some environments on this application.</comment>');
      $output->writeln("<comment>Check that you have the following permissions: <options=bold>$perm_string</></comment>");
    }
    return $mappings;
  }

  protected function createSshKey(string $filename, string $password): string {
    $key_file_path = $this->doCreateSshKey($filename, $password);
    $this->setSshKeyFilepath(basename($key_file_path));
    if (!$this->sshKeyIsAddedToKeychain()) {
      $this->addSshKeyToAgent($this->publicSshKeyFilepath, $password);
    }
    return $key_file_path;
  }

  private function doCreateSshKey(string $filename, string $password): string {
    $filepath = $this->sshDir . '/' . $filename;
    if (file_exists($filepath)) {
      throw new AcquiaCliException('An SSH key with the filename {filepath} already exists. Delete it and retry', ['filepath' => $filename]);
    }

    $this->localMachineHelper->checkRequiredBinariesExist(['ssh-keygen']);
    $process = $this->localMachineHelper->execute([
      'ssh-keygen',
      '-t',
      'rsa',
      '-b',
      '4096',
      '-f',
      $filepath,
      '-N',
      $password,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException($process->getOutput() . $process->getErrorOutput());
    }

    return $filepath;
  }

  protected function determineFilename(): string {
    return $this->determineOption(
      'filename',
      FALSE,
      Closure::fromCallable([$this, 'validateFilename']),
      static function ($value) {
        return $value ? trim($value) : '';},
      'id_rsa_acquia'
    );
  }

  private function validateFilename(string $filename): string {
    $violations = Validation::createValidator()->validate($filename, [
      new Length(['min' => 5]),
      new NotBlank(),
      new Regex(['pattern' => '/^\S*$/', 'message' => 'The value may not contain spaces']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $filename;
  }

  protected function determinePassword(): string {
    return $this->determineOption(
      'password',
      TRUE,
      Closure::fromCallable([$this, 'validatePassword']),
      static function ($value) {
        return $value ? trim($value) : '';
      }
    );
  }

  private function validatePassword(string $password): string {
    $violations = Validation::createValidator()->validate($password, [
      new Length(['min' => 5]),
      new NotBlank(),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $password;
  }

  private function keyHasUploaded(Client $acquia_cloud_client, string $public_key): bool {
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    foreach ($cloud_keys as $cloud_key) {
      if (trim($cloud_key->public_key) === trim($public_key)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @return array
   */
  protected function determinePublicSshKey(string $filepath = NULL): array {
    if ($filepath) {
      $filepath = $this->localMachineHelper->getLocalFilepath($filepath);
    }
    elseif ($this->input->hasOption('filepath') && $this->input->getOption('filepath')) {
      $filepath = $this->localMachineHelper->getLocalFilepath($this->input->getOption('filepath'));
    }

    if ($filepath) {
      if (!$this->localMachineHelper->getFilesystem()->exists($filepath)) {
        throw new AcquiaCliException('The filepath {filepath} is not valid', ['filepath' => $filepath]);
      }
      if (!str_contains($filepath, '.pub')) {
        throw new AcquiaCliException('The filepath {filepath} does not have the .pub extension', ['filepath' => $filepath]);
      }
      $public_key = $this->localMachineHelper->readFile($filepath);
      $chosen_local_key = basename($filepath);
    }
    else {
      // Get local key and contents.
      $local_keys = $this->findLocalSshKeys();
      $chosen_local_key = $this->promptChooseLocalSshKey($local_keys);
      $public_key = $this->getLocalSshKeyContents($local_keys, $chosen_local_key);
    }

    return [$chosen_local_key, $public_key];
  }

  /**
   * @param \Symfony\Component\Finder\SplFileInfo[] $local_keys
   */
  private function promptChooseLocalSshKey(array $local_keys): string {
    $labels = [];
    foreach ($local_keys as $local_key) {
      $labels[] = $local_key->getFilename();
    }
    $question = new ChoiceQuestion(
      'Choose a local SSH key to upload to the Cloud Platform',
      $labels
    );
    return $this->io->askQuestion($question);
  }

  protected function determineSshKeyLabel(): string {
    return $this->determineOption('label', FALSE, Closure::fromCallable([$this, 'validateSshKeyLabel']), Closure::fromCallable([$this, 'normalizeSshKeyLabel']));
  }

  /**
   * @param $label
   */
  private function validateSshKeyLabel($label): mixed {
    if (trim($label) === '') {
      throw new RuntimeException('The label cannot be empty');
    }

    return $label;
  }

  /**
   * @param \Symfony\Component\Finder\SplFileInfo[] $local_keys
   */
  private function getLocalSshKeyContents(array $local_keys, string $chosen_local_key): string {
    $filepath = '';
    foreach ($local_keys as $local_key) {
      if ($local_key->getFilename() === $chosen_local_key) {
        $filepath = $local_key->getRealPath();
        break;
      }
    }
    return $this->localMachineHelper->readFile($filepath);
  }

  protected function uploadSshKey(string $label, string $public_key): void {
    // @todo If a key with this label already exists, let the user try again.
    $sshKeys = new SshKeys($this->cloudApiClientService->getClient());
    $sshKeys->create($label, $public_key);

    // Wait for the key to register on the Cloud Platform.
    if ($this->input->hasOption('no-wait') && $this->input->getOption('no-wait') === FALSE) {
      if ($this->input->isInteractive() && !$this->promptWaitForSsh($this->io)) {
        $this->io->success('Your SSH key has been successfully uploaded to the Cloud Platform.');
        return;
      }

      if ($this->keyHasUploaded($this->cloudApiClientService->getClient(), $public_key)) {
        $this->pollAcquiaCloudUntilSshSuccess($this->output);
      }
    }
  }

}
