<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\AcquiaCliApplication;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use drupol\phposinfo\OsInfo;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\KeyValueStore\JsonFileStore;
use Zumba\Amplitude\Amplitude;

class TelemetryHelper {

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private $output;

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
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
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  private $cloudDatastore;

  /**
   * TelemetryHelper constructor.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Acquia\Cli\Helpers\ClientService $cloud_api
   * @param \Webmozart\KeyValueStore\JsonFileStore $acli_datastore
   * @param \Webmozart\KeyValueStore\JsonFileStore $cloud_datastore
   */
  public function __construct(
    InputInterface $input,
    OutputInterface $output,
    ClientService $cloud_api,
    JsonFileStore $acli_datastore,
    JsonFileStore $cloud_datastore
  ) {
    $this->input = $input;
    $this->output = $output;
    $this->cloudApi = $cloud_api;
    $this->cloudDatastore = $cloud_datastore;
    $this->acliDatastore = $acli_datastore;
  }

  /**
   * Initializes Amplitude.
   *
   * @param \Zumba\Amplitude\Amplitude $amplitude
   * @param string $app_version
   *
   * @throws \Exception
   */
  public function initializeAmplitude(Amplitude $amplitude, $app_version): void {
    $send_telemetry = $this->acliDatastore->get(DataStoreContract::SEND_TELEMETRY);
    $amplitude->setOptOut(!$send_telemetry);

    if (!$send_telemetry) {
      return;
    }
    try {
      $amplitude->init('956516c74386447a3148c2cc36013ac3');
      // Method chaining breaks Prophecy?
      // @see https://github.com/phpspec/prophecy/issues/25
      $amplitude->setDeviceId(OsInfo::uuid());
      $amplitude->setUserProperties($this->getTelemetryUserData($app_version));
      $amplitude->setUserId($this->getUserId());
      $amplitude->logQueuedEvents();
    } catch (IdentityProviderException $e) {
      // If something is wrong with the Cloud API client, don't bother users.
    }
  }

  /**
   * Get telemetry user data.
   *
   * @return array
   *   Telemetry user data.
   * @throws \Exception
   */
  protected function getTelemetryUserData($app_version): array {
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
  protected function getUserData(): ?array {
    $user = $this->acliDatastore->get(DataStoreContract::USER);
    if (!$user && AcquiaCliApplication::isMachineAuthenticated($this->cloudDatastore)) {
      $this->setDefaultUserData();
      $user = $this->acliDatastore->get(DataStoreContract::USER);
    }

    return $user;
  }

  /**
   * This requires the machine to be authenticated.
   */
  protected function setDefaultUserData(): array {
    $user = $this->getDefaultUserData();
    $this->acliDatastore->set(DataStoreContract::USER, $user);
  }

  /**
   * This requires the machine to be authenticated.
   *
   * @return array
   */
  protected function getDefaultUserData(): array {
    $account = new Account($this->cloudApi->getClient());
    $user = [
      'uuid' => $account->get()->uuid,
      'is_acquian' => substr($account->get()->mail, -10, 10) === 'acquia.com'
    ];
    return $user;
  }

}
