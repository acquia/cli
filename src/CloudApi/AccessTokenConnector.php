<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\Connector;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\RequestInterface;

class AccessTokenConnector extends Connector
{
    /**
     * @var \League\OAuth2\Client\Provider\GenericProvider
     */
    protected AbstractProvider $provider;

    /**
     * @param array<string> $config
     */
    public function __construct(array $config, string $baseUri = null, string $urlAccessToken = null)
    {
        $this->accessToken = new AccessToken(['access_token' => $config['access_token']]);
        parent::__construct($config, $baseUri, $urlAccessToken);
    }

    public function createRequest(string $verb, string $path): RequestInterface
    {
        if ($file = getenv('ACLI_ACCESS_TOKEN_FILE')) {
            if (!file_exists($file)) {
                throw new AcquiaCliException('Access token file not found at {file}', ['file' => $file]);
            }
            $this->accessToken = new AccessToken(['access_token' => trim(file_get_contents($file), "\"\n")]);
        }
        return $this->provider->getAuthenticatedRequest(
            $verb,
            $this->getBaseUri() . $path,
            $this->accessToken
        );
    }

    public function setProvider(
        GenericProvider $provider
    ): void {
        $this->provider = $provider;
    }

    public function getAccessToken(): AccessToken
    {
        return $this->accessToken;
    }
}
