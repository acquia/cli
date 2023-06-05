<?php

declare(strict_types = 1);

namespace Acquia\Cli\AcsfApi;

use AcquiaCloudApi\Connector\Connector;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;

class AcsfConnector extends Connector {

  /**
   * @param array<string> $config
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

  /**
   * @param array<string> $options
   */
  public function sendRequest(string $verb, string $path, array $options): ResponseInterface {
    return $this->client->request($verb, $path, $options);
  }

}
