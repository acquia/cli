<?php

namespace Acquia\Ads\Connector;

use AcquiaCloudApi\Connector\Connector;
use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;

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
        $stack->push(
            new CacheMiddleware(new PrivateCacheStrategy(new DoctrineCacheStorage(new FilesystemCache(__DIR__ . '/../../cache')))),
            'cache'
        );
        $client = new GuzzleClient(['handler' => $stack]);
        return $client->send($request, $options);
    }
}
