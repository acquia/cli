<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use Acquia\Cli\ApiCredentialsInterface;

/**
 * Configuration for the Sites Aggregation Service (SAS) API.
 *
 * Authentication is identical to the Cloud API (the same Accounts-issued
 * key/secret and access token), which is why the services file feeds this
 * class's data from the standard cloud credentials. This class exists to
 * provide the SAS base URI, the only piece of configuration unique to SAS.
 */
class SasCredentials implements ApiCredentialsInterface
{
    public function getCloudKey(): ?string
    {
        // Unused: the SAS connector is configured from cloud.credentials
        // directly. See config/prod/services.yml.
        return null;
    }

    public function getCloudSecret(): ?string
    {
        // Unused: see getCloudKey().
        return null;
    }

    /**
     * Get the SAS API base URI.
     *
     * @todo DXBE-20: Confirm the env var name and the production URI with the
     *   SAS team. Follows the ACLI_CLOUD_API_BASE_URI convention.
     */
    public function getBaseUri(): ?string
    {
        if ($uri = getenv('ACLI_SAS_API_BASE_URI')) {
            return $uri;
        }

        return 'https://sites-aggregation-service.acquia.com/api';
    }
}
