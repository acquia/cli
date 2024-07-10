<?php

declare(strict_types=1);

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;

class AcsfClientService extends ClientService
{
    public function __construct(AcsfConnectorFactory $connectorFactory, Application $application, AcsfCredentials $cloudCredentials)
    {
        parent::__construct($connectorFactory, $application, $cloudCredentials);
    }

    public function getClient(): AcsfClient
    {
        $client = AcsfClient::factory($this->connector);
        $this->configureClient($client);

        return $client;
    }

    protected function checkAuthentication(): bool
    {
        return ($this->credentials->getCloudKey() && $this->credentials->getCloudSecret());
    }
}
