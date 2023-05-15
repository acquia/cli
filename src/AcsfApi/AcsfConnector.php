<?php

namespace Acquia\Cli\AcsfApi;

use AcquiaCloudApi\Connector\Connector;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;

/**
 * AcsfConnector class.
 */
class AcsfConnector extends Connector {

  /**
   * @param array $config
   * @param string|null $baseUri
   * @param string|null $urlAccessToken
   */
  public function __construct(array $config, string $baseUri = NULL, string $urlAccessToken = NULL) {
    parent::__construct($config, $baseUri, $urlAccessToken);

    $this->client = new GuzzleClient([
      'auth' => [
        $config['key'],
        $config['secret'],
      ],
      'base_uri' => $this->getBaseUri(),
    ]);
  }

  public function sendRequest($verb, $path, $options): ResponseInterface {
    return $this->client->request($verb, $path, $options);
  }

}
