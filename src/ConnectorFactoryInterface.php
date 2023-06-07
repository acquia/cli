<?php

declare(strict_types = 1);

namespace Acquia\Cli;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use AcquiaCloudApi\Connector\Connector;

interface ConnectorFactoryInterface {

  public function createConnector(): Connector|AccessTokenConnector;

}
