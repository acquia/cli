<?php

declare(strict_types=1);

namespace Acquia\Cli\AcsfApi;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\DataStore\CloudDataStore;

class AcsfClientService extends ClientService
{
    public function __construct(AcsfConnectorFactory $connectorFactory, Application $application, AcsfCredentials $cloudCredentials, ?CloudDataStore $datastore = null)
    {
        parent::__construct($connectorFactory, $application, $cloudCredentials, $datastore);
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
