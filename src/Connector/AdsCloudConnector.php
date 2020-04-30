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
     *
     * @param $verb
     * @param $path
     * @param $options
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function sendRequest($verb, $path, $options)
    {
        $stack = HandlerStack::create();
        $stack->push(
          // This will respect the header's cache response!
          // Unfortunately, that means it actually doesn't help us at all with Cloud API
          // given that all responses will have a 'no-cache' header.
            new CacheMiddleware(new PrivateCacheStrategy(new DoctrineCacheStorage(new FilesystemCache(__DIR__ . '/../../cache')))),
            'cache'
        );
        $client = new GuzzleClient(['handler' => $stack]);
        $request = $this->createRequest($verb, $path);

        return $client->send($request, $options);
    }
}
