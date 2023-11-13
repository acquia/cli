<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshCommandTrait;
use Acquia\Cli\Output\Spinner\Spinner;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\SshKeys;
use AcquiaCloudApi\Response\IdeResponse;
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

  protected function setSshKeyFilepath(string $privateSshKeyFilename): void {
    $this->privateSshKeyFilename = $privateSshKeyFilename;
    $this->privateSshKeyFilepath = $this->sshDir . '/' . $this->privateSshKeyFilename;
    $this->publicSshKeyFilepath = $this->privateSshKeyFilepath . '.pub';
  }

  public static function getIdeSshKeyLabel(IdeResponse $ide): string {
    return self::normalizeSshKeyLabel('IDE_' . $ide->label . '_' . $ide->uuid);
  }

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
  protected function normalizePublicSshKey(string $publicKey): string {
    $parts = explode('== ', $publicKey);
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
      $keyContents = $this->normalizePublicSshKey($this->localMachineHelper->readFile($this->publicSshKeyFilepath));
      return str_contains($process->getOutput(), $keyContents);
    }
    return FALSE;
  }

  /**
   * Adds a given password protected local SSH key to the local keychain.
   *
   * @param string $filepath The filepath of the private SSH key.
   */
  protected function addSshKeyToAgent(string $filepath, string $password): void {
    // We must use a separate script to mimic user input due to the limitations of the `ssh-add` command.
    // @see https://www.linux.com/topic/networking/manage-ssh-key-file-passphrase/
    $tempFilepath = $this->localMachineHelper->getFilesystem()->tempnam(sys_get_temp_dir(), 'acli');
    $this->localMachineHelper->writeFile($tempFilepath, <<<'EOT'
#!/usr/bin/env bash
echo $SSH_PASS
EOT
    );
    $this->localMachineHelper->getFilesystem()->chmod($tempFilepath, 0755);
    $privateKeyFilepath = str_replace('.pub', '', $filepath);
    $process = $this->localMachineHelper->executeFromCmd('SSH_PASS=' . $password . ' DISPLAY=1 SSH_ASKPASS=' . $tempFilepath . ' ssh-add ' . $privateKeyFilepath, NULL, NULL, FALSE);
    $this->localMachineHelper->getFilesystem()->remove($tempFilepath);
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
    $cloudAppUuid = $this->determineCloudApplication(TRUE);
    $permissions = $this->cloudApiClientService->getClient()->request('get', "/applications/$cloudAppUuid/permissions");
    $perms = array_column($permissions, 'name');
    $mappings = $this->checkPermissions($perms, $cloudAppUuid, $output);
    foreach ($mappings as $envName => $config) {
      $spinner = new Spinner($output, 4);
      $spinner->setMessage("Waiting for the key to become available in Cloud Platform $envName environments");
      $spinner->start();
      $mappings[$envName]['timer'] = $loop->addPeriodicTimer($spinner->interval(),
        static function () use ($spinner): void {
          $spinner->advance();
        });
      $mappings[$envName]['spinner'] = $spinner;
    }
    $callback = function () use ($output, $loop, &$mappings, &$timers, $startTime): void {
      foreach ($mappings as $envName => $config) {
        try {
          $process = $this->sshHelper->executeCommand($config['ssh_target'], ['ls'], FALSE);
          if (($process->getExitCode() === 128 && $envName === 'git') || $process->isSuccessful()) {
            // SSH key is available on this host, but may be pending on others.
            $config['spinner']->finish();
            $loop->cancelTimer($config['timer']);
            unset($mappings[$envName]);
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

  /**
   * @return array<mixed>
   */
  private function checkPermissions(array $userPerms, string $cloudAppUuid, OutputInterface $output): array {
    $mappings = [];
    $requiredPerms = ['add ssh key to git', 'add ssh key to non-prod', 'add ssh key to prod'];
    foreach ($requiredPerms as $index => $requiredPerm) {
      if (in_array($requiredPerm, $userPerms, TRUE)) {
        switch ($requiredPerm) {
          case 'add ssh key to git':
            $fullUrl = $this->getAnyVcsUrl($cloudAppUuid);
            $urlParts = explode(':', $fullUrl);
            $mappings['git']['ssh_target'] = $urlParts[0];
            break;
          case 'add ssh key to non-prod':
            if ($nonProdEnv = $this->getAnyNonProdAhEnvironment($cloudAppUuid)) {
              $mappings['nonprod']['ssh_target'] = $nonProdEnv;
            }
            break;
          case 'add ssh key to prod':
            if ($prodEnv = $this->getAnyProdAhEnvironment($cloudAppUuid)) {
              $mappings['prod']['ssh_target'] = $prodEnv;
            }
            break;
        }
        unset($requiredPerms[$index]);
      }
    }
    if (!empty($requiredPerms)) {
      $permString = implode(", ", $requiredPerms);
      $output->writeln('<comment>You do not have access to some environments on this application.</comment>');
      $output->writeln("<comment>Check that you have the following permissions: <options=bold>$permString</></comment>");
    }
    return $mappings;
  }

  protected function createSshKey(string $filename, string $password): string {
    $keyFilePath = $this->doCreateSshKey($filename, $password);
    $this->setSshKeyFilepath(basename($keyFilePath));
    if (!$this->sshKeyIsAddedToKeychain()) {
      $this->addSshKeyToAgent($this->publicSshKeyFilepath, $password);
    }
    return $keyFilePath;
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
      $this->validateFilename(...),
      static function (mixed $value) {
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
      $this->validatePassword(...),
      static function (mixed $value) {
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

  private function keyHasUploaded(Client $acquiaCloudClient, string $publicKey): bool {
    $sshKeys = new SshKeys($acquiaCloudClient);
    foreach ($sshKeys->getAll() as $cloudKey) {
      if (trim($cloudKey->public_key) === trim($publicKey)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @return array<mixed>
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
      $publicKey = $this->localMachineHelper->readFile($filepath);
      $chosenLocalKey = basename($filepath);
    }
    else {
      // Get local key and contents.
      $localKeys = $this->findLocalSshKeys();
      $chosenLocalKey = $this->promptChooseLocalSshKey($localKeys);
      $publicKey = $this->getLocalSshKeyContents($localKeys, $chosenLocalKey);
    }

    return [$chosenLocalKey, $publicKey];
  }

  private function promptChooseLocalSshKey(array $localKeys): string {
    $labels = [];
    foreach ($localKeys as $localKey) {
      $labels[] = $localKey->getFilename();
    }
    $question = new ChoiceQuestion(
      'Choose a local SSH key to upload to the Cloud Platform',
      $labels
    );
    return $this->io->askQuestion($question);
  }

  protected function determineSshKeyLabel(): string {
    return $this->determineOption('label', FALSE, $this->validateSshKeyLabel(...), $this->normalizeSshKeyLabel(...));
  }

  private function validateSshKeyLabel(mixed $label): mixed {
    if (trim($label) === '') {
      throw new RuntimeException('The label cannot be empty');
    }

    return $label;
  }

  private function getLocalSshKeyContents(array $localKeys, string $chosenLocalKey): string {
    $filepath = '';
    foreach ($localKeys as $localKey) {
      if ($localKey->getFilename() === $chosenLocalKey) {
        $filepath = $localKey->getRealPath();
        break;
      }
    }
    return $this->localMachineHelper->readFile($filepath);
  }

  protected function uploadSshKey(string $label, string $publicKey): void {
    // @todo If a key with this label already exists, let the user try again.
    $sshKeys = new SshKeys($this->cloudApiClientService->getClient());
    $sshKeys->create($label, $publicKey);

    // Wait for the key to register on the Cloud Platform.
    if ($this->input->hasOption('no-wait') && $this->input->getOption('no-wait') === FALSE) {
      if ($this->input->isInteractive() && !$this->promptWaitForSsh($this->io)) {
        $this->io->success('Your SSH key has been successfully uploaded to the Cloud Platform.');
        return;
      }

      if ($this->keyHasUploaded($this->cloudApiClientService->getClient(), $publicKey)) {
        $this->pollAcquiaCloudUntilSshSuccess($this->output);
      }
    }
  }

  protected static function getFingerprint(mixed $sshPublicKey): string {
    if (!str_starts_with($sshPublicKey, 'ssh-rsa ')) {
      throw new AcquiaCliException('SSH keys must start with "ssh-rsa ".');
    }
    $content = explode(' ', $sshPublicKey, 3);
    return base64_encode(hash('sha256', base64_decode($content[1]), TRUE));
  }

}
