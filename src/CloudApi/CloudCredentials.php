<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;

class CloudCredentials implements ApiCredentialsInterface
{
    /**
     * CloudCredentials constructor.
     */
    public function __construct(private CloudDataStore $datastoreCloud)
    {
    }

    public function getCloudAccessToken(): ?string
    {
        if ($token = getenv('ACLI_ACCESS_TOKEN')) {
            return $token;
        }

        if ($file = getenv('ACLI_ACCESS_TOKEN_FILE')) {
            if (!file_exists($file)) {
                throw new AcquiaCliException('Access token file not found at {file}', ['file' => $file]);
            }
            return trim(file_get_contents($file), "\"\n");
        }

        return null;
    }

    public function getCloudAccessTokenExpiry(): ?string
    {
        if ($token = getenv('ACLI_ACCESS_TOKEN_EXPIRY')) {
            return $token;
        }

        if ($file = getenv('ACLI_ACCESS_TOKEN_EXPIRY_FILE')) {
            if (!file_exists($file)) {
                throw new AcquiaCliException('Access token expiry file not found at {file}', ['file' => $file]);
            }
            return trim(file_get_contents($file), "\"\n");
        }

        return null;
    }

    public function getCloudKey(): ?string
    {
        if ($key = getenv('ACLI_KEY')) {
            return $key;
        }

        if ($this->datastoreCloud->get('acli_key')) {
            return $this->datastoreCloud->get('acli_key');
        }

        return null;
    }

    public function getCloudSecret(): ?string
    {
        if ($secret = getenv('ACLI_SECRET')) {
            return $secret;
        }

        $acliKey = $this->getCloudKey();
        if ($this->datastoreCloud->get('keys')) {
            $keys = $this->datastoreCloud->get('keys');
            if (is_array($keys) && array_key_exists($acliKey, $keys)) {
                return $this->datastoreCloud->get('keys')[$acliKey]['secret'];
            }
        }

        return null;
    }

    public function getBaseUri(): ?string
    {
        if ($uri = getenv('ACLI_CLOUD_API_BASE_URI')) {
            return $uri;
        }
        return null;
    }

    /**
     * Base URI for Cloud API v3 (MEO) commands registered under `api:v3:*`.
     * Set `ACLI_CLOUD_API_V3_BASE_URI` to point to a specific environment:
     *   Dev:     https://gateway.dev.api.acquia.io/v3
     *   Stage:   https://staging.api.acquia.com/v3  (tentative)
     *   Prod:    TBD — hardcode here once confirmed
     */
    public function getV3BaseUri(): ?string
    {
        if ($uri = getenv('ACLI_CLOUD_API_V3_BASE_URI')) {
            return $uri;
        }
        return null;
    }

    public function getAccountsUri(): ?string
    {
        if ($uri = getenv('ACLI_CLOUD_API_ACCOUNTS_URI')) {
            return $uri;
        }
        return null;
    }
}
