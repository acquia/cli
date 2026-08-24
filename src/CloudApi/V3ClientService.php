<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\Application;

/**
 * Client service for Cloud API v3 (MEO) commands. Shares credentials with the
 * v2 ClientService but resolves its base URI via CloudCredentials::getV3BaseUri(),
 * so `ACLI_CLOUD_API_V3_BASE_URI` can override the prod gateway for dev/stage traffic.
 */
class V3ClientService extends ClientService
{
    public function __construct(ConnectorFactory $connectorFactory, Application $application, CloudCredentials $credentials)
    {
        parent::__construct($connectorFactory, $application, $credentials);
    }
}
