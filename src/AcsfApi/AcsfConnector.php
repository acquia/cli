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
   * @param string|null $base_uri
   * @param string|null $url_access_token
   */
  public function __construct(array $config, string $base_uri = NULL, string $url_access_token = NULL) {
    parent::__construct($config, $base_uri, $url_access_token);

    $this->client = new GuzzleClient([
      'base_uri' => $this->getBaseUri(),
      'auth' => [
        $config['key'],
        $config['secret'],
      ],
    ]);
  }

  /**
   * @inheritdoc
   */
  public function sendRequest($verb, $path, $options): ResponseInterface {
    return $this->client->request($verb, $path, $options);
  }

}
