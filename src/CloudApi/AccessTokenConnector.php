<?php

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\Connector;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\RequestInterface;

/**
 * Class AccessTokenConnector
 */
class AccessTokenConnector extends Connector {

  /**
   * @var \League\OAuth2\Client\Provider\GenericProvider
   */
  protected AbstractProvider $provider;

  /**
   * @inheritdoc
   */
  public function __construct(array $config, string $base_uri = NULL, string $url_access_token = NULL) {
    $this->accessToken = new AccessToken(['access_token' => $config['access_token']]);
    parent::__construct($config, $base_uri, $url_access_token);
  }

  /**
   * @inheritdoc
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function createRequest($verb, $path): RequestInterface {
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

  /**
   */
  public function setProvider(
    GenericProvider $provider
  ): void {
    $this->provider = $provider;
  }

  /**
   */
  public function getAccessToken(): AccessToken {
    return $this->accessToken;
  }

}
