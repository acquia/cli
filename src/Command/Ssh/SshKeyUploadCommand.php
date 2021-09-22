<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use AcquiaCloudApi\Connector\Client;
use Closure;
use React\EventLoop\Loop;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class SshKeyUploadCommand.
 */
class SshKeyUploadCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:upload';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Upload a local SSH key to the Cloud Platform')
      ->addOption('filepath', NULL, InputOption::VALUE_REQUIRED, 'The filepath of the public SSH key to upload')
      ->addOption('label', NULL, InputOption::VALUE_REQUIRED, 'The SSH key label to be used with the Cloud Platform')
      ->addOption('no-wait', NULL, InputOption::VALUE_NONE, "Don't wait for the SSH key to be uploaded to the Cloud Platform");
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    [$chosen_local_key, $public_key] = $this->determinePublicSshKey();
    $label = $this->determineSshKeyLabel($input, $output);

    $options = [
      'form_params' => [
        'label' => $label,
        'public_key' => $public_key,
      ],
    ];
    $response = $acquia_cloud_client->makeRequest('post', '/account/ssh-keys', $options);
    if ($response->getStatusCode() !== 202) {
      throw new AcquiaCliException($response->getBody()->getContents());
    }

    $this->output->writeln("<info>Uploaded $chosen_local_key to the Cloud Platform with label $label</info>");

    // Wait for the key to register on the Cloud Platform.
    if ($input->getOption('no-wait') === FALSE) {
      $this->output->write('Waiting for new key to be provisioned on the Cloud Platform...');
      $this->pollAcquiaCloud($output, $acquia_cloud_client, $public_key);
    }

    return 0;
  }

  /**
   * @return array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determinePublicSshKey(): array {
    if ($this->input->getOption('filepath')) {
      $filepath = $this->localMachineHelper
        ->getLocalFilepath($this->input->getOption('filepath'));
      if (!file_exists($filepath)) {
        throw new AcquiaCliException('The filepath {filepath} is not valid', ['filepath' => $filepath]);
      }
      if (strpos($filepath, '.pub') === FALSE) {
        throw new AcquiaCliException('The filepath {filepath} does not have the .pub extension', ['filepath' => $filepath]);
      }
      $public_key = $this->localMachineHelper->readFile($filepath);
      $chosen_local_key = basename($filepath);
    } else {
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
    if ($input->getOption('label')) {
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
   * @return false|string
   * @throws \Exception
   */
  protected function getLocalSshKeyContents($local_keys, string $chosen_local_key) {
    foreach ($local_keys as $local_key) {
      if ($local_key->getFilename() === $chosen_local_key) {
        $filepath = $local_key->getRealPath();
        break;
      }
    }
    return $this->localMachineHelper->readFile($filepath);
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param string $public_key
   */
  protected function pollAcquiaCloud(
    OutputInterface $output,
    Client $acquia_cloud_client,
    string $public_key
  ): void {
    // Create a loop to periodically poll the Cloud Platform.
    $loop = Loop::get();
    $spinner = LoopHelper::addSpinnerToLoop($loop, 'Waiting for SSH key to become available on the Cloud Platform...', $output);

    // Poll Cloud every 5 seconds.
    $loop->addPeriodicTimer(5, function () use ($output, $loop, $acquia_cloud_client, $public_key, $spinner) {
      // @todo Change this to test an actual ssh connection, not just Cloud API.
      // But which server do we check a connection to?
      $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
      foreach ($cloud_keys as $cloud_key) {
        if (trim($cloud_key->public_key) === trim($public_key)) {
          LoopHelper::finishSpinner($spinner);
          $output->writeln("\n<info>Your SSH key is ready for use.</info>");
          $loop->stop();
        }
      }
    });
    LoopHelper::addTimeoutToLoop($loop, 10, $spinner, $output);
    $loop->run();
  }

}
