<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\ConnectorFactoryInterface;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use League\OAuth2\Client\Token\AccessToken;

class ConnectorFactory implements ConnectorFactoryInterface
{
    /**
     * @param array<string> $config
     */
    public function __construct(protected array $config, protected ?string $baseUri = null, protected ?string $accountsUri = null)
    {
    }

    public function createConnector(): ConnectorInterface
    {
        $connector = $this->buildConnector();

        // If the AH_CODEBASE_UUID environment variable is set, that means
        // it's a MEO subscription. For MEO, we need to rewrite the API request
        // path so that MEO-specific endpoints are used and the correct
        // endpoint can be selected based on the codebase.
        if (getenv('AH_CODEBASE_UUID')) {
            return new PathRewriteConnector($connector);
        }

        return $connector;
    }

    private function buildConnector(): ConnectorInterface
    {
        // A defined key & secret takes priority.
        if ($this->config['key'] && $this->config['secret']) {
            return new Connector($this->config, $this->baseUri, $this->accountsUri);
        }

        // Fall back to a valid access token.
        if ($this->config['accessToken']) {
            $accessToken = $this->createAccessToken();
            if (!$accessToken->hasExpired()) {
                // @todo Add debug log entry indicating that access token is being used.
                return new AccessTokenConnector([
                    'access_token' => $accessToken,
                    'key' => null,
                    'secret' => null,
                ], $this->baseUri, $this->accountsUri);
            }
        }

        // Fall back to an unauthenticated request.
        return new Connector($this->config, $this->baseUri, $this->accountsUri);
    }

    private function createAccessToken(): AccessToken
    {
        return new AccessToken([
            'access_token' => $this->config['accessToken'],
            'expires' => $this->config['accessTokenExpiry'],
        ]);
    }
}
