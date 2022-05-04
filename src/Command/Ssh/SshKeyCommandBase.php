<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Helpers\SshCommandTrait;
use AcquiaCloudApi\Response\IdeResponse;
use Closure;
use React\EventLoop\Loop;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

/**
 * Class SshKeyCommandBase.
 */
abstract class SshKeyCommandBase extends CommandBase {

  use SshCommandTrait;

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

    if ($process->isSuccessful()) {
      $key_contents = $this->normalizePublicSshKey($this->localMachineHelper->readFile($this->publicSshKeyFilepath));
      return strpos($process->getOutput(), $key_contents) !== FALSE;
    }
    return FALSE;
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
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Exception
   */
  protected function pollAcquiaCloudUntilSshSuccess(
    OutputInterface $output
  ): void {
    // Create a loop to periodically poll the Cloud Platform.
    $loop = Loop::get();
    $spinner_git = LoopHelper::addSpinnerToLoop($loop, 'Waiting for the key to become available in Cloud Platform git', $output);
    $spinner_nonprod = LoopHelper::addSpinnerToLoop($loop, 'Waiting for the key to become available in Cloud Platform non-prod environments', $output);
    $spinner_prod = LoopHelper::addSpinnerToLoop($loop, 'Waiting for the key to become available in Cloud Platform prod environments', $output);
    $cloud_app_uuid = $this->determineCloudApplication(TRUE);
    $permissions = $this->cloudApiClientService->getClient()->request('get', "/applications/{$cloud_app_uuid}/permissions");
    $perms = array_column($permissions, 'name');
    $vcs_url = $environment_nonprod = $environment_prod = NULL;
    if (in_array('add ssh key to git', $perms, TRUE)) {
      $poll_git = TRUE;
      $full_url = $this->getAnyVcsUrl($cloud_app_uuid);
      $url_parts = explode(':', $full_url);
      $vcs_url = $url_parts[0];
    }
    else {
      $poll_git = FALSE;
      $output->writeln('<comment>You do not have access to Cloud Platform git on this application and will not be able to clone your codebase to this IDE. Check that you have the <options=bold>add ssh key to git</> permission. Documentation on Cloud Teams permissions: <href=https://docs.acquia.com/cloud-platform/access/teams/permissions/default/>https://docs.acquia.com/cloud-platform/access/teams/permissions/default/</>');
    }
    if (in_array('add ssh key to non-prod', $perms, TRUE)) {
      $poll_nonprod = TRUE;
      $environment_nonprod = $this->getAnyNonProdAhEnvironment($cloud_app_uuid);
    }
    else {
      $poll_nonprod = FALSE;
      $output->writeln('<comment>You do not have access to Cloud Platform non-prod environments on this application and will not be able to clone your non-prod sites to this IDE. Check that you have the <options=bold>add ssh key to non-prod environments</> permission. Documentation on Cloud Teams permissions: <href=https://docs.acquia.com/cloud-platform/access/teams/permissions/default/>https://docs.acquia.com/cloud-platform/access/teams/permissions/default/</>');
    }
    if (in_array('add ssh key to prod', $perms, TRUE)) {
      $poll_prod = TRUE;
      $environment_prod = $this->getAnyProdAhEnvironment($cloud_app_uuid);
    }
    else {
      $poll_prod = FALSE;
      $output->writeln('<comment>You do not have access to Cloud Platform prod environments on this application and will not be able to clone your prod sites to this IDE. Check that you have the <options=bold>add ssh key to prod environments</> permission. Documentation on Cloud Teams permissions: <href=https://docs.acquia.com/cloud-platform/access/teams/permissions/default/>https://docs.acquia.com/cloud-platform/access/teams/permissions/default/</>');
    }

    // Poll Cloud every 5 seconds.
    $loop->addPeriodicTimer(5, function () use ($output, $loop, $spinner_git, $spinner_nonprod, $spinner_prod, &$poll_git, &$poll_nonprod, &$poll_prod, $vcs_url, $environment_nonprod, $environment_prod) {
      if ($poll_git) {
        try {
          $process = $this->sshHelper->executeCommandUrl($vcs_url, ['ls'], FALSE);
          // Interactive Git shell is disabled, the best we can hope for is a 128 exit code.
          if ($process->getExitCode() === 128) {
            $poll_git = FALSE;
            LoopHelper::finishSpinner($spinner_git);
          }
          else {
            $this->logger->debug($process->getOutput() . $process->getErrorOutput());
          }
        } catch (AcquiaCliException $exception) {
          // Do nothing. Keep waiting and looping and logging.
          $this->logger->debug($exception->getMessage());
        }
      }
      if ($poll_nonprod) {
        try {
          $process = $this->sshHelper->executeCommand($environment_nonprod, ['ls'], FALSE);
          if ($process->isSuccessful()) {
            $poll_nonprod = FALSE;
            LoopHelper::finishSpinner($spinner_nonprod);
          }
          else {
            $this->logger->debug($process->getOutput() . $process->getErrorOutput());
          }
        } catch (AcquiaCliException $exception) {
          // Do nothing. Keep waiting and looping and logging.
          $this->logger->debug($exception->getMessage());
        }
      }
      if ($poll_prod) {
        try {
          $process = $this->sshHelper->executeCommand($environment_prod, ['ls'], FALSE);
          if ($process->isSuccessful()) {
            $poll_prod = FALSE;
            LoopHelper::finishSpinner($spinner_prod);
          }
          else {
            $this->logger->debug($process->getOutput() . $process->getErrorOutput());
          }
        } catch (AcquiaCliException $exception) {
          // Do nothing. Keep waiting and looping and logging.
          $this->logger->debug($exception->getMessage());
        }
      }
      if (!$poll_git && !$poll_nonprod && !$poll_prod) {
        $loop->stop();
        $output->writeln("\n<info>Your SSH key is ready for use!</info>\n");
      }
    });
    $loop->addTimer(10 * 60, function () use ($output) {
      $output->writeln("\n<comment>This is taking longer than usual. It will happen eventually!</comment>\n");
    });
    LoopHelper::addTimeoutToLoop($loop, 30, $spinner_git);
    LoopHelper::addTimeoutToLoop($loop, 30, $spinner_nonprod);
    LoopHelper::addTimeoutToLoop($loop, 30, $spinner_prod);
    $loop->run();
  }

  /**
   * @param string $filename
   * @param string $password
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function createSshKey(string $filename, string $password) {
    $key_file_path = $this->doCreateSshKey($filename, $password);
    $this->setSshKeyFilepath(basename($key_file_path));
    if (!$this->sshKeyIsAddedToKeychain()) {
      $this->addSshKeyToAgent($this->publicSshKeyFilepath, $password);
    }
    return $key_file_path;
  }

  /**
   * @param string $filename
   * @param string $password
   *
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function doCreateSshKey($filename, $password): string {
    $filepath = $this->sshDir . '/' . $filename;
    if (file_exists($filepath)) {
      throw new AcquiaCliException('An SSH key with the filename {filepath} already exists. Please delete it and retry', ['filepath' => $filename]);
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

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   */
  protected function determineFilename(InputInterface $input, OutputInterface $output): string {
    if ($input->getOption('filename')) {
      $filename = $input->getOption('filename');
      $this->validateFilename($filename);
    }
    else {
      $default = 'id_rsa_acquia';
      $question = new Question("Please enter a filename for your new local SSH key. Press enter to use default value", $default);
      $question->setNormalizer(static function ($value) {
        return $value ? trim($value) : '';
      });
      $question->setValidator(Closure::fromCallable([$this, 'validateFilename']));
      $filename = $this->io->askQuestion($question);
    }

    return $filename;
  }

  /**
   * @param string $filename
   *
   * @return mixed
   */
  protected function validateFilename($filename) {
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

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   * @throws \Exception
   */
  protected function determinePassword(InputInterface $input, OutputInterface $output): string {
    if ($input->getOption('password')) {
      $password = $input->getOption('password');
      $this->validatePassword($password);
      return $password;
    }
    if ($input->isInteractive()) {
      $question = new Question('Enter a password for your SSH key');
      $question->setHidden($this->localMachineHelper->useTty());
      $question->setNormalizer(static function ($value) {
        return $value ? trim($value) : '';
      });
      $question->setValidator(Closure::fromCallable([$this, 'validatePassword']));
      return $this->io->askQuestion($question);
    }

    throw new AcquiaCliException('Could not determine the SSH key password. Either use the --password option or else run this command in an interactive shell.');
  }

  /**
   * @param string $password
   *
   * @return string
   */
  protected function validatePassword($password) {
    $violations = Validation::createValidator()->validate($password, [
      new Length(['min' => 5]),
      new NotBlank(),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $password;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param string $public_key
   *
   * @return bool
   */
  protected function keyHasUploaded($acquia_cloud_client, $public_key): bool {
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    foreach ($cloud_keys as $cloud_key) {
      if (trim($cloud_key->public_key) === trim($public_key)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @param null $filepath
   *
   * @return array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function determinePublicSshKey($filepath = NULL): array {
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
      if (strpos($filepath, '.pub') === FALSE) {
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
   *
   * @return string
   */
  protected function promptChooseLocalSshKey($local_keys): string {
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

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   */
  protected function determineSshKeyLabel(InputInterface $input, OutputInterface $output): string {
    if ($input->hasOption('label') && $input->getOption('label')) {
      $label = $input->getOption('label');
      $label = SshKeyCommandBase::normalizeSshKeyLabel($label);
      $label = $this->validateSshKeyLabel($label);
    }
    else {
      $question = new Question('Please enter a Cloud Platform label for this SSH key');
      $question->setNormalizer(Closure::fromCallable([$this, 'normalizeSshKeyLabel']));
      $question->setValidator(Closure::fromCallable([$this, 'validateSshKeyLabel']));
      $label = $this->io->askQuestion($question);
    }

    return $label;
  }

  /**
   * @param $label
   *
   * @return mixed
   */
  protected function validateSshKeyLabel($label) {
    if (trim($label) === '') {
      throw new RuntimeException('The label cannot be empty');
    }

    return $label;
  }

  /**
   * @param \Symfony\Component\Finder\SplFileInfo[] $local_keys
   * @param string $chosen_local_key
   *
   * @return string
   * @throws \Exception
   */
  protected function getLocalSshKeyContents(array $local_keys, string $chosen_local_key): string {
    $filepath = '';
    foreach ($local_keys as $local_key) {
      if ($local_key->getFilename() === $chosen_local_key) {
        $filepath = $local_key->getRealPath();
        break;
      }
    }
    return $this->localMachineHelper->readFile($filepath);
  }

  /**
   * @param string $label
   * @param string $chosen_local_key
   * @param string $public_key
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function uploadSshKey(string $label, string $chosen_local_key, string $public_key) {
    $options = [
      'form_params' => [
        'label' => $label,
        'public_key' => $public_key,
      ],
    ];

    // @todo If a key with this label already exists, let the user try again.
    $response = $this->cloudApiClientService->getClient()->makeRequest('post', '/account/ssh-keys', $options);
    if ($response->getStatusCode() !== 202) {
      throw new AcquiaCliException($response->getBody()->getContents());
    }

    $this->output->writeln("<info>Uploaded $chosen_local_key to the Cloud Platform with label $label</info>");

    // Wait for the key to register on the Cloud Platform.
    if ($this->input->hasOption('no-wait') && $this->input->getOption('no-wait') === FALSE) {
      if ($this->input->isInteractive()) {
        $this->io->note("It may take some time before the SSH key is installed on all of your application's web servers.");
        $answer = $this->io->confirm("Would you like to wait until Cloud Platform is ready?");
        if (!$answer) {
          $this->io->success('Your SSH key has been successfully uploaded to Cloud Platform.');
          return;
        }
      }

      if ($this->keyHasUploaded($this->cloudApiClientService->getClient(), $public_key)) {
        $this->pollAcquiaCloudUntilSshSuccess($this->output);
      }
    }
  }

}
