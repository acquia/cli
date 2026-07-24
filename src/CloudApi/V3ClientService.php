<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\Application;

/**
 * Client service for Cloud API v3 (MEO) commands. Shares credentials with the
 * v2 ClientService but resolves its base URI via CloudCredentials::getV3BaseUri(),
 * so `ACLI_CLOUD_API_V3_BASE_URI` can point v3 traffic at the dedicated gateway
 * once it exists. Until then, getV3BaseUri() falls back to the v2 URL.
 */
class V3ClientService extends ClientService
{
    public function __construct(ConnectorFactory $connectorFactory, Application $application, CloudCredentials $credentials)
    {
        parent::__construct($connectorFactory, $application, $credentials);
    }
}
