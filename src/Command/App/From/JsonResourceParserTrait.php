<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From;

/**
 * Trait for parsing JSON from a PHP resource.
 */
trait JsonResourceParserTrait
{
    /**
     * Gets the decoded contents from a PHP resource containing JSON.
     *
     * @param resource $resource
     *   The resource from which to read and decode JSON.
     * @return mixed
     *   The decoded JSON, usually an array.
     */
    protected static function parseJsonResource($resource): mixed
    {
        assert(is_resource($resource));
        $json = stream_get_contents($resource);
        return json_decode($json, flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
    }
}
