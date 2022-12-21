<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\DataStore\CloudDataStore;
use Bugsnag\Client;
use Bugsnag\Handler;

class TelemetryHelper {

  /**
   * @var \Acquia\Cli\DataStore\CloudDataStore
   */
  private CloudDataStore $datastoreCloud;

  /**
   * TelemetryHelper constructor.
   *
   * @param \Acquia\Cli\DataStore\CloudDataStore $datastoreCloud
   */
  public function __construct(
    CloudDataStore $datastoreCloud
  ) {
    $this->datastoreCloud = $datastoreCloud;
  }

  public function initialize(): void {
    $this->initializeBugsnag();
  }

  public function initializeBugsnag(): void {
    $send_telemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
    if ($send_telemetry === FALSE) {
      return;
    }
    // It's safe-ish to make this key public.
    // @see https://github.com/bugsnag/bugsnag-js/issues/595
    // @todo verify that this actually catches errors and exceptions
    $bugsnag = Client::make('7b8b2f87d710e3ab29ec0fd6d9ca0474');
    Handler::register($bugsnag);
  }

}
