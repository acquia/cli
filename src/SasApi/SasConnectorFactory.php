<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\ConnectorFactoryInterface;
use AcquiaCloudApi\Connector\ConnectorInterface;
use League\OAuth2\Client\Token\AccessToken;

class SasConnectorFactory implements ConnectorFactoryInterface
{
    /**
     * @param array<string, string|null> $config
     */
    public function __construct(protected array $config, protected ?string $baseUri = null, protected ?string $accountsUri = null)
    {
    }

    public function createConnector(): ConnectorInterface
    {
        // A defined key & secret takes priority.
        if ($this->config['key'] && $this->config['secret']) {
            return new SasConnector($this->config, $this->baseUri, $this->accountsUri);
        }

        // Fall back to a valid access token (e.g. a bot token in CI).
        if (!empty($this->config['accessToken'])) {
            $accessToken = $this->createAccessToken();
            if (!$accessToken->hasExpired()) {
                return new AccessTokenConnector([
                    'access_token' => $accessToken,
                    'key' => null,
                    'secret' => null,
                ], $this->baseUri, $this->accountsUri);
            }
        }

        // Fall back to an unauthenticated request.
        return new SasConnector($this->config, $this->baseUri, $this->accountsUri);
    }

    private function createAccessToken(): AccessToken
    {
        return new AccessToken([
            'access_token' => $this->config['accessToken'],
            'expires' => $this->config['accessTokenExpiry'],
        ]);
    }
}
