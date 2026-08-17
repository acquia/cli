<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use AcquiaCloudApi\Connector\Connector;

/**
 * Connector for the Sites Aggregation Service (SAS) API.
 *
 * SAS shares the Accounts authentication layer with the Cloud API, so the
 * parent class provides OAuth2 client-credentials tokens (Bearer auth) with
 * no changes. The only difference is the base URI requests are sent to.
 */
class SasConnector extends Connector
{
    /**
     * @param array<string, string|null> $config
     */
    public function __construct(array $config, ?string $baseUri = null, ?string $urlAccessToken = null)
    {
        parent::__construct($config, $baseUri, $urlAccessToken);
    }
}
