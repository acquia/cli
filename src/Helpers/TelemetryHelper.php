<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use loophp\phposinfo\OsInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
   * @var \Acquia\Cli\DataStore\YamlStore
   */
  private $acliDatastore;

  /**
   * @var \Acquia\Cli\CloudApi\ClientService
   */
  private $cloudApiClientService;

  /**
   * @var \Acquia\Cli\DataStore\CloudDataStore
   */
  private $datastoreCloud;

  /**
   * TelemetryHelper constructor.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Acquia\Cli\CloudApi\ClientService $client_service
   * @param \Acquia\Cli\DataStore\AcquiaCliDatastore $datastoreAcli
   * @param \Acquia\Cli\DataStore\CloudDataStore $datastoreCloud
   */
  public function __construct(
    InputInterface $input,
    OutputInterface $output,
    ClientService $client_service,
    AcquiaCliDatastore $datastoreAcli,
    CloudDataStore $datastoreCloud
  ) {
    $this->input = $input;
    $this->output = $output;
    $this->cloudApiClientService = $client_service;
    $this->datastoreCloud = $datastoreCloud;
    $this->acliDatastore = $datastoreAcli;
  }

  /**
   * Initializes Amplitude.
   *
   * @throws \Exception
   */
  public function initializeAmplitude(): void {
    $send_telemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
    $amplitude = Amplitude::getInstance();
    $amplitude->setOptOut($send_telemetry === FALSE);

    if ($send_telemetry === FALSE) {
      return;
    }
    try {
      $amplitude->init('0bdb9aae813d628e1388b22bc2cf79f2');
      // Method chaining breaks Prophecy?
      // @see https://github.com/phpspec/prophecy/issues/25
      $amplitude->setDeviceId(OsInfo::uuid());
      $amplitude->setUserProperties($this->getTelemetryUserData());
      $amplitude->setUserId($this->getUserId());
      $amplitude->logQueuedEvents();
    }
    catch (IdentityProviderException $e) {
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
  private function getTelemetryUserData(): array {
    $data = [
      'ah_env' => AcquiaDrupalEnvironmentDetector::getAhEnv(),
      'ah_group' => AcquiaDrupalEnvironmentDetector::getAhGroup(),
      'ah_app_uuid' => getenv('AH_APPLICATION_UUID'),
      'ah_realm' => getenv('AH_REALM'),
      'ah_non_production' => getenv('AH_NON_PRODUCTION'),
      'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
      'CI' => getenv('CI'),
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
  private function getUserId(): ?string {
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
  private function getUserData(): ?array {
    $user = $this->datastoreCloud->get(DataStoreContract::USER);
    if (!$user && $this->cloudApiClientService->isMachineAuthenticated($this->datastoreCloud)) {
      $this->setDefaultUserData();
      $user = $this->datastoreCloud->get(DataStoreContract::USER);
    }

    return $user;
  }

  /**
   * This requires the machine to be authenticated.
   */
  private function setDefaultUserData(): void {
    $user = $this->getDefaultUserData();
    $this->datastoreCloud->set(DataStoreContract::USER, $user);
  }

  /**
   * This requires the machine to be authenticated.
   *
   * @return array
   */
  private function getDefaultUserData(): array {
    // @todo Cache this!
    $account = new Account($this->cloudApiClientService->getClient());
    return [
      'uuid' => $account->get()->uuid,
      'is_acquian' => substr($account->get()->mail, -10, 10) === 'acquia.com'
    ];
  }

}
