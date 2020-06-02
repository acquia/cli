<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\AcquiaCliApplication;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use drupol\phposinfo\OsInfo;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Webmozart\KeyValueStore\JsonFileStore;
use Zumba\Amplitude\Amplitude;

class TelemetryHelper {

  /** @var \Symfony\Component\Console\Output\OutputInterface */
  private $output;

  /** @var \Symfony\Component\Console\Input\InputInterface */
  private $input;

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  private $acliDatastore;

  /**
   * @var \Acquia\Cli\Helpers\ClientService
   */
  private $cloudApi;

  /**
   * @var \Symfony\Component\Console\Helper\QuestionHelper
   */
  private $questionHelper;

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  private $cloudDatastore;

  public function __construct(
    InputInterface $input,
    OutputInterface $output,
    ClientService $cloud_api,
    JsonFileStore $acli_datastore,
    JsonFileStore $cloud_datastore,
    QuestionHelper $question_helper
  ) {
    $this->input = $input;
    $this->output = $output;
    $this->cloudApi = $cloud_api;
    $this->cloudDatastore = $cloud_datastore;
    $this->acliDatastore = $acli_datastore;
    $this->questionHelper = $question_helper;
  }

  /**
   * Initializes Amplitude.
   *
   * @param \Zumba\Amplitude\Amplitude $amplitude
   *
   * @throws \Exception
   */
  public function initializeAmplitude(Amplitude $amplitude, $app_version): void {
    $send_telemetry = $this->acliDatastore->get(DataStoreContract::SEND_TELEMETRY);
    $amplitude->setOptOut(!$send_telemetry);
    if (!$send_telemetry) {
      return;
    }
    $amplitude->init('956516c74386447a3148c2cc36013ac3');
    // Method chaining breaks Prophecy?
    // @see https://github.com/phpspec/prophecy/issues/25
    $amplitude->setDeviceId(OsInfo::uuid());
    $amplitude->setUserProperties($this->getTelemetryUserData($app_version));
    try {
      $amplitude->setUserId($this->getUserId());
    } catch (IdentityProviderException $e) {
      // If something is wrong with the Cloud API client, don't bother users.
    }
    $amplitude->logQueuedEvents();
  }

  /**
   * Check if telemetry preference is set, prompt if not.
   */
  public function checkTelemetryPreference(): void {
    $send_telemetry = $this->acliDatastore->get(DataStoreContract::SEND_TELEMETRY);
    if (!isset($send_telemetry) && $this->input->isInteractive()) {
      $this->output->writeln('We strive to give you the best tools for development.');
      $this->output->writeln('You can really help us improve by sharing anonymous performance and usage data.');
      $question = new ConfirmationQuestion('<question>Would you like to share anonymous performance usage and data?</question>', TRUE);
      $pref = $this->questionHelper->ask($this->input, $this->output, $question);
      $this->acliDatastore->set(DataStoreContract::SEND_TELEMETRY, $pref);
      if ($pref) {
        $this->output->writeln('Awesome! Thank you for helping!');
      }
      else {
        $this->output->writeln('Ok, no data will be collected and shared with us.');
        $this->output->writeln('We take privacy seriously.');
        $this->output->writeln('If you change your mind, run <comment>acli telemetry</comment>.');
      }
    }
  }

  /**
   * Get telemetry user data.
   *
   * @return array
   *   Telemetry user data.
   * @throws \Exception
   */
  public function getTelemetryUserData($app_version): array {
    $data = [
      'app_version' => $app_version,
      // phpcs:ignore
      'platform' => OsInfo::family(),
      'os_name' => OsInfo::os(),
      'os_version' => OsInfo::version(),
      'ah_env' => AcquiaDrupalEnvironmentDetector::getAhEnv(),
      'ah_group' => AcquiaDrupalEnvironmentDetector::getAhGroup(),
      'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
    ];
    try {
      $user = $this->getUserData();
      if (isset($user['is_acquian'])) {
        $data['is_acquian'] = $user['is_acquian'];
      }
    }
    catch (IdentityProviderException $e) {
      // If something is wrong with the Cloud API client, don't bother users.
    }
    return $data;
  }

  /**
   * Get user uuid.
   *
   * @return string|null
   *   User UUID from Cloud.
   * @throws \Exception
   */
  public function getUserId(): ?string {
    $user = $this->getUserData();
    if ($user && isset($user['uuid'])) {
      return $user['uuid'];
    }

    return NULL;
  }

  /**
   * Get user data.
   *
   * @return array|null
   *   User account data from Cloud.
   * @throws \Exception
   */
  public function getUserData(): ?array {
    $user = $this->acliDatastore->get(DataStoreContract::USER);

    if (!$user && AcquiaCliApplication::isMachineAuthenticated($this->cloudDatastore)) {
      $user = $this->setDefaultUserData();
    }

    return $user;
  }

  /**
   * @return array
   */
  protected function setDefaultUserData(): array {
    $account = new Account($this->cloudApi->getClient());
    $user = [
      'uuid' => $account->get()->uuid,
      'is_acquian' => substr($account->get()->mail, -10, 10) === 'acquia.com'
    ];
    $this->acliDatastore->set(DataStoreContract::USER, $user);
    return $user;
  }

}
