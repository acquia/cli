<?php

declare(strict_types=1);

namespace Acquia\Cli;

use AcquiaCloudApi\Connector\ConnectorInterface;

interface ConnectorFactoryInterface
{
    public function createConnector(): ConnectorInterface;
}
