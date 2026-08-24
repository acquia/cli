<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use Acquia\Cli\CloudApi\CloudCredentials;

/**
 * Credentials for the Sites Aggregation Service (SAS) API.
 *
 * SAS shares the Cloud API auth layer (same key/secret, same OAuth token
 * endpoint), so all credential logic is inherited. Only the base URI differs:
 * SAS is a standalone service, not the Cloud API gateway.
 */
class SasCredentials extends CloudCredentials
{
    private const DEFAULT_BASE_URI = 'https://sites-aggregation-service-prod.prod.cicd.acquia.io/api';

    /**
     * Base URI for SAS. Override with ACLI_SAS_BASE_URI for non-production
     * environments (e.g. .dev/.qa/.staging.cicd.acquia.io).
     */
    public function getBaseUri(): ?string
    {
        // Treat a set-but-empty value the same as unset.
        $uri = getenv('ACLI_SAS_BASE_URI');
        return !empty($uri) ? $uri : self::DEFAULT_BASE_URI;
    }
}
