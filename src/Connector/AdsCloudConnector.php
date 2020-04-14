<?php

namespace Acquia\Ads\Connector;

use AcquiaCloudApi\Connector\Connector;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;

/**
 * Class AdsCloudConnector
 */
class AdsCloudConnector extends Connector
{
    /**
     * Adds caching handler to the default AcquiaCloudApi\Connector\Connector.
     */
    public function sendRequest($verb, $path, $options)
    {
        $request = $this->createRequest($verb, $path);

        $stack = HandlerStack::create();
        $stack->push(new CacheMiddleware(), 'cache');
        $client = new GuzzleClient(['handler' => $stack]);
        return $client->send($request, $options);
    }
}
