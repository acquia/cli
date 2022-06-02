<?php

namespace Acquia\Cli\AcsfApi;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Exception\ApiErrorException;
use Psr\Http\Message\ResponseInterface;

class AcsfClient extends Client {

  /**
   * @inheritdoc
   */
  public function processResponse(ResponseInterface $response) {
    $body_json = $response->getBody();
    $body = json_decode($body_json);

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
    if (in_array(substr($response->getStatusCode(), 0, 1), [4, 5]) && property_exists($body, 'message')) {
      $body->error = $response->getStatusCode();
      throw new ApiErrorException($body);
    }

    return $body;
  }

}
