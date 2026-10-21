<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\Client;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class CloudApiClient extends Client
{
    public function processResponse(ResponseInterface $response): mixed
    {
        try {
            return parent::processResponse($response);
        } catch (JsonException) {
            throw self::nonJsonResponseException($response);
        }
    }

    /**
     * Outages and gateway errors return HTML pages instead of JSON, which
     * json_decode() would otherwise report only as "Syntax error".
     */
    public static function nonJsonResponseException(ResponseInterface $response): AcquiaCliException
    {
        $message = 'The Acquia API returned HTTP {status} {reason} with a response that is not JSON. The service may be temporarily unavailable; try again later.';
        $requestId = $response->getHeaderLine('X-Request-Id');
        if ($requestId !== '') {
            $message .= ' Request ID: {requestId}';
        }
        return new AcquiaCliException($message, [
            'reason' => $response->getReasonPhrase(),
            'requestId' => $requestId,
            'status' => $response->getStatusCode(),
        ]);
    }
}
