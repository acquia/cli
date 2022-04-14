<?php

namespace Acquia\Cli\AcsfApi;

use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Webmozart\PathUtil\Path;

/**
 * Factory producing Acquia Cloud Api clients.
 *
 * This class is only necessary as a testing shim, so that we can prophesize
 * client queries. Consumers could otherwise just call
 * Client::factory($connector) directly.
 *
 * @package Acquia\Cli\Helpers
 */
class AcsfConnector extends Connector {

  /**
   * @param array $config
   * @param string|null $base_uri
   */
  public function __construct(array $config, string $base_uri = NULL) {
    $this->baseUri = ConnectorInterface::BASE_URI;
    if ($base_uri) {
      $this->baseUri = $base_uri;
    }

    $this->client = new GuzzleClient([
      'base_uri' => $this->baseUri,
      'auth' => [
        $config['key'],
        $config['secret'],
      ],
    ]);
  }

  /**
   * @inheritdoc
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function sendRequest($verb, $path, $options) {
    return $this->client->request($verb, $path, $options);
  }
}