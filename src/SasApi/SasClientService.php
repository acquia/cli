<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\ConnectorFactory;

/**
 * Client service for the Sites Aggregation Service (SAS) API.
 *
 * Same auth as Cloud API but a different base URI, resolved by
 * SasCredentials. Wire with the sas.connector_factory service so requests go
 * to SAS rather than the Cloud API gateway.
 */
class SasClientService extends ClientService
{
    public function __construct(ConnectorFactory $connectorFactory, Application $application, SasCredentials $credentials)
    {
        parent::__construct($connectorFactory, $application, $credentials);
    }
}
