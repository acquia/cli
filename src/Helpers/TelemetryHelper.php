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
use Bugsnag\Report;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use loophp\phposinfo\OsInfo;
use Symfony\Component\Filesystem\Path;
use Zumba\Amplitude\Amplitude;

class TelemetryHelper {

  public function __construct(
    private readonly ClientService $cloudApiClientService,
    private readonly CloudDataStore $datastoreCloud,
    private readonly Application $application,
    private readonly ?string $amplitudeKey = '',
    private readonly ?string $bugSnagKey = ''
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
    $bugsnag->registerCallback(function (Report $report): bool {
      // Exclude errors that we can't control.
      switch (TRUE) {
        // Exclude reports from app:from, which bootstraps Drupal.
        case str_starts_with($report->getContext(), 'GET'):
          // Exclude memory exhaustion errors.
        case str_starts_with($report->getContext(), 'Allowed memory size'):
          // Exclude i/o errors.
        case str_starts_with($report->getContext(), 'fgets'):
          return FALSE;
      }
      // Set user info.
      $userId = $this->getUserId();
      if (isset($userId)) {
        $report->setUser([
          'id' => $userId,
        ]);
      }
      $context = $report->getContext();
      // Strip working directory and binary from context.
      if (str_contains($context, 'acli ')) {
        $context = substr($context, strpos($context, 'acli ') + 5);
      }
      // Strip sensitive parameters from context.
      if (str_contains($context, "--password")) {
        $context = substr($context, 0, strpos($context, "--password") + 10) . 'REDACTED';
      }
      $report->setContext($context);
      return TRUE;
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
   * @return array<mixed> Telemetry user data.
   */
  private function getTelemetryUserData(): array {
    $data = [
      'ah_app_uuid' => getenv('AH_APPLICATION_UUID'),
      'ah_env' => AcquiaDrupalEnvironmentDetector::getAhEnv(),
      'ah_group' => AcquiaDrupalEnvironmentDetector::getAhGroup(),
      'ah_non_production' => getenv('AH_NON_PRODUCTION'),
      'ah_realm' => getenv('AH_REALM'),
      'CI' => getenv('CI'),
      'env_provider' => $this->getEnvironmentProvider(),
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

  public static function getEnvironmentProvider(): ?string {
    $providers = self::getProviders();

    // Check for environment variables.
    foreach ($providers as $provider => $vars) {
      foreach ($vars as $var) {
        if (getenv($var) !== FALSE)
          return $provider;
      }
    }

    return NULL;
  }

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
   * @return array<mixed>|null User account data from Cloud.
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

  /**
   * @infection-ignore-all
   *   Skipping infection testing for this because, it most cases, we expect that when a row from this array is changed
   *   it won't affect the return value.
   *
   * @return array<mixed>
   *   An array of providers and their associated environment variables.
   */
  public static function getProviders(): array {
    // Define the environment variables associated with each provider.
    return [
      'lando' => ['LANDO'],
      'ddev' => ['IS_DDEV_PROJECT'],
      // Check Lando and DDEV first because the hijack AH_SITE_ENVIRONMENT.
      'acquia' => ['AH_SITE_ENVIRONMENT'],
      'bamboo' => ['BAMBOO_BUILDNUMBER'],
      'beanstalk' => ['BEANSTALK_ENVIRONMENT'],
      'bitbucket' => ['BITBUCKET_BUILD_NUMBER'],
      'bitrise' => ['BITRISE_IO'],
      'buddy' => ['BUDDY_WORKSPACE_ID'],
      'circleci' => ['CIRCLECI'],
      'codebuild' => ['CODEBUILD_BUILD_ID'],
      'docksal' => ['DOCKSAL_VERSION'],
      'drone' => ['DRONE'],
      'github' => ['GITHUB_ACTIONS'],
      'gitlab' => ['GITLAB_CI'],
      'heroku' => ['HEROKU_TEST_RUN_ID'],
      'jenkins' => ['JENKINS_URL'],
      'pantheon' => ['PANTHEON_ENVIRONMENT'],
      'pipelines' => ['PIPELINE_ENV'],
      'platformsh' => ['PLATFORM_ENVIRONMENT'],
      'teamcity' => ['TEAMCITY_VERSION'],
      'travis' => ['TRAVIS'],
    ];
  }

}
