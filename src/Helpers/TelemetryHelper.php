<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\DataStore\YamlStore;
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
   * @var \Acquia\Cli\DataStore\YamlStore
   */
  private $acliDatastore;

  /**
   * @var \Acquia\Cli\Helpers\ClientService
   */
  private $cloudApi;

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  private $datastoreCloud;

  /**
   * TelemetryHelper constructor.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Acquia\Cli\Helpers\ClientService $cloud_api
   * @param \Acquia\Cli\DataStore\YamlStore $datastoreAcli
   * @param \Webmozart\KeyValueStore\JsonFileStore $datastoreCloud
   */
  public function __construct(
    InputInterface $input,
    OutputInterface $output,
    ClientService $cloud_api,
    YamlStore $datastoreAcli,
    JsonFileStore $datastoreCloud
  ) {
    $this->input = $input;
    $this->output = $output;
    $this->cloudApi = $cloud_api;
    $this->datastoreCloud = $datastoreCloud;
    $this->acliDatastore = $datastoreAcli;
  }

  /**
   * Initializes Amplitude.
   *
   * @param \Zumba\Amplitude\Amplitude $amplitude
   *
   * @throws \Exception
   */
  public function initializeAmplitude(Amplitude $amplitude): void {
    $send_telemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
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
  protected function getTelemetryUserData(): array {
    $data = [
      'ah_env' => AcquiaDrupalEnvironmentDetector::getAhEnv(),
      'ah_group' => AcquiaDrupalEnvironmentDetector::getAhGroup(),
      'ah_app_uuid' => getenv('AH_APPLICATION_UUID'),
      'ah_realm' => getenv('AH_REALM'),
      'ah_non_production' => getenv('AH_NON_PRODUCTION'),
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
    $user = $this->datastoreCloud->get(DataStoreContract::USER);
    if (!$user && CommandBase::isMachineAuthenticated($this->datastoreCloud)) {
      $this->setDefaultUserData();
      $user = $this->datastoreCloud->get(DataStoreContract::USER);
    }

    return $user;
  }

  /**
   * This requires the machine to be authenticated.
   */
  protected function setDefaultUserData(): void {
    $user = $this->getDefaultUserData();
    $this->datastoreCloud->set(DataStoreContract::USER, $user);
  }

  /**
   * This requires the machine to be authenticated.
   *
   * @return array
   */
  protected function getDefaultUserData(): array {
    // @todo Cache this!
    $account = new Account($this->cloudApi->getClient());
    $user = [
      'uuid' => $account->get()->uuid,
      'is_acquian' => substr($account->get()->mail, -10, 10) === 'acquia.com'
    ];
    return $user;
  }

}
