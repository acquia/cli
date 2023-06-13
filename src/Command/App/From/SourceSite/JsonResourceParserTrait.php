<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\SourceSite;

/**
 * Trait for parsing JSON from a PHP resource.
 */
trait JsonResourceParserTrait {

  /**
   * Gets the decoded contents from a PHP resource containing JSON.
   *
   * @param resource $resource
   *   The resource from which to read and decode JSON.
   *
   * @return mixed
   *   The decoded JSON, usually an array.
   *
   * @throws \JsonException
   *   Thrown if the given resource contains malformed JSON.
   */
  protected static function parseJsonResource($resource) {
    assert(is_resource($resource));
    $json = stream_get_contents($resource);
    return json_decode($json, TRUE, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
  }

}
