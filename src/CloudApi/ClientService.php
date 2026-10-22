<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\Application;
use Acquia\Cli\ConnectorFactoryInterface;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\ConnectorInterface;

/**
 * Factory producing Acquia Cloud Api clients.
 *
 * This class is only necessary as a testing shim, so that we can prophesize
 * client queries. Consumers could otherwise just call
 * Client::factory($connector) directly.
 */
class ClientService
{
    /**
     * Stands in for the telemetry ID when there isn't one to send.
     */
    private const TELEMETRY_ID_NONE = 'null';

    protected ConnectorInterface $connector;

    protected ConnectorFactoryInterface|ConnectorFactory $connectorFactory;

    protected Application $application;

    protected ?bool $machineIsAuthenticated = null;

    public function __construct(ConnectorFactoryInterface $connectorFactory, Application $application, protected ApiCredentialsInterface $credentials, protected ?CloudDataStore $datastore = null)
    {
        $this->connectorFactory = $connectorFactory;
        $this->setConnector($connectorFactory->createConnector());
        $this->setApplication($application);
    }

    public function setConnector(ConnectorInterface $connector): void
    {
        $this->connector = $connector;
    }

    private function setApplication(Application $application): void
    {
        $this->application = $application;
    }

    public function getClient(): Client
    {
        $client = Client::factory($this->connector);
        $this->configureClient($client);

        return $client;
    }

    protected function configureClient(Client $client): void
    {
        $provider = TelemetryHelper::getEnvironmentProvider();
        $userAgent = sprintf(
            'Acquia CLI (%s, %s%s)',
            $this->application->getVersion(),
            TelemetryHelper::isTelemetryEnabled($this->datastore)
                ? (TelemetryHelper::getMachineId() ?: self::TELEMETRY_ID_NONE)
                : self::TELEMETRY_ID_NONE,
            $provider !== null ? ', ' . $provider : '',
        );
        $customHeaders = [
            'User-Agent' => [$userAgent],
        ];
        if ($uuid = getenv("REMOTEIDE_UUID")) {
            $customHeaders['X-Cloud-IDE-UUID'] = $uuid;
        }
        $client->addOption('headers', $customHeaders);
    }

    public function isMachineAuthenticated(): bool
    {
        if ($this->machineIsAuthenticated !== null) {
            return $this->machineIsAuthenticated;
        }
        $this->machineIsAuthenticated = $this->checkAuthentication();
        return $this->machineIsAuthenticated;
    }

    protected function checkAuthentication(): bool
    {
        return (
            $this->credentials->getCloudAccessToken() ||
            ($this->credentials->getCloudKey() && $this->credentials->getCloudSecret()) ||
            ($this->credentials->getCloudDeviceAccessToken() !== null)
        );
    }
}
