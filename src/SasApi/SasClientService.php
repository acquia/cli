<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;

class SasClientService extends ClientService
{
    public function __construct(SasConnectorFactory $connectorFactory, Application $application, ApiCredentialsInterface $credentials)
    {
        parent::__construct($connectorFactory, $application, $credentials);
    }

    public function getClient(): SasClient
    {
        $client = SasClient::factory($this->connector);
        // @infection-ignore-all configureClient() only sets User-Agent headers
        // (inherited SDK behavior); its removal is not observable via the
        // returned client in a unit test.
        $this->configureClient($client);

        return $client;
    }
}
