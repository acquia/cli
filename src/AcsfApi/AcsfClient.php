<?php

namespace Acquia\Cli\AcsfApi;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Exception\ApiErrorException;
use Psr\Http\Message\ResponseInterface;

class AcsfClient extends Client {

  /**
   * @inheritdoc
   */
  public function processResponse(ResponseInterface $response): mixed {
    $body_json = $response->getBody();
    $body = json_decode($body_json, FALSE, 512, JSON_THROW_ON_ERROR);

    // ACSF sometimes returns an array rather than an object.
    if (is_array($body)) {
      return $body;
    }

    if (property_exists($body, '_embedded') && property_exists($body->_embedded, 'items')) {
      return $body->_embedded->items;
    }

    if (property_exists($body, 'error') && property_exists($body, 'message')) {
      throw new ApiErrorException($body);
    }
    // Throw error for 4xx and 5xx responses.
    if (property_exists($body, 'message') && in_array(substr($response->getStatusCode(), 0, 1), [4, 5], TRUE)) {
      $body->error = $response->getStatusCode();
      throw new ApiErrorException($body);
    }

    return $body;
  }

}
