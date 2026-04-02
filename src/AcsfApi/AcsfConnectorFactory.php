<?php

declare(strict_types=1);

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\ConnectorFactoryInterface;
use AcquiaCloudApi\Connector\ConnectorInterface;

class AcsfConnectorFactory implements ConnectorFactoryInterface
{
    /**
     * @param array<string> $config
     */
    public function __construct(protected array $config, protected ?string $baseUri = null)
    {
    }

    public function createConnector(): ConnectorInterface
    {
        return new AcsfConnector($this->config, $this->baseUri);
    }
}
