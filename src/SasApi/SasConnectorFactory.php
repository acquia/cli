<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use Acquia\Cli\ConnectorFactoryInterface;
use AcquiaCloudApi\Connector\ConnectorInterface;

class SasConnectorFactory implements ConnectorFactoryInterface
{
    /**
     * @param array<string, string|null> $config
     */
    public function __construct(protected array $config, protected ?string $baseUri = null, protected ?string $accountsUri = null)
    {
    }

    public function createConnector(): ConnectorInterface
    {
        return new SasConnector($this->config, $this->baseUri, $this->accountsUri);
    }
}
