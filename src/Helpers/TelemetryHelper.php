<?php

declare(strict_types = 1);

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use Bugsnag\Client;
use Bugsnag\Handler;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use loophp\phposinfo\OsInfo;
use Symfony\Component\Filesystem\Path;
use Zumba\Amplitude\Amplitude;

class TelemetryHelper {

  public function __construct(
    private ClientService $cloudApiClientService,
    private CloudDataStore $datastoreCloud,
    private Application $application,
    private ?string $amplitudeKey = '',
    private ?string $bugSnagKey = ''
  ) {
  }

  public function initialize(): void {
    $this->initializeAmplitude();
    $this->initializeBugsnag();
  }

  public function initializeBugsnag(): void {
    if (empty($this->bugSnagKey)) {
      return;
    }
    $sendTelemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
    if ($sendTelemetry === FALSE) {
      return;
    }
    // It's safe-ish to make this key public.
    // @see https://github.com/bugsnag/bugsnag-js/issues/595
    $bugsnag = Client::make($this->bugSnagKey);
    $bugsnag->setAppVersion($this->application->getVersion());
    $bugsnag->setProjectRoot(Path::join(__DIR__, '..'));
    $bugsnag->registerCallback(function (mixed $report): void {
      $userId = $this->getUserId();
      if (isset($userId)) {
        $report->setUser([
          'id' => $userId,
        ]);
      }
    });
    $bugsnag->registerCallback(function (mixed $report): void {
      $context = $report->getContext();
      // Strip working directory and binary from context.
      if (str_contains($context, 'acli ')) {
        $context = substr($context, strpos($context, 'acli ') + 5);
      }
      // Strip sensitive parameters from context
      if (str_contains($context, "--password")) {
        $context = substr($context, 0, strpos($context, "--password") + 10) . 'REDACTED';
      }
      $report->setContext($context);
    });
    Handler::register($bugsnag);
  }

  /**
   * Initializes Amplitude.
   */
  public function initializeAmplitude(): void {
    if (empty($this->amplitudeKey)) {
      return;
    }
    $sendTelemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
    $amplitude = Amplitude::getInstance();
    $amplitude->setOptOut($sendTelemetry === FALSE);

    if ($sendTelemetry === FALSE) {
      return;
    }
    try {
      $amplitude->init($this->amplitudeKey);
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
   * @return array<mixed>
   *   Telemetry user data.
   */
  private function getTelemetryUserData(): array {
    $data = [
      'ah_app_uuid' => getenv('AH_APPLICATION_UUID'),
      'ah_env' => AcquiaDrupalEnvironmentDetector::getAhEnv(),
      'ah_group' => AcquiaDrupalEnvironmentDetector::getAhGroup(),
      'ah_non_production' => getenv('AH_NON_PRODUCTION'),
      'ah_realm' => getenv('AH_REALM'),
      'CI' => getenv('CI'),
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
   * @return array<mixed>|null
   *   User account data from Cloud.
   */
  private function getUserData(): ?array {
    $user = $this->datastoreCloud->get(DataStoreContract::USER);
    if (!$user && $this->cloudApiClientService->isMachineAuthenticated()) {
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
   * @return array<mixed>
   */
  private function getDefaultUserData(): array {
    // @todo Cache this!
    $account = new Account($this->cloudApiClientService->getClient());
    return [
      'is_acquian' => str_ends_with($account->get()->mail, 'acquia.com'),
      'uuid' => $account->get()->uuid,
    ];
  }

}
