<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App\From\Recommendation;

use Acquia\Cli\Command\App\From\JsonResourceParserTrait;
use ArrayIterator;
use Exception;

/**
 * Represents a command configuration.
 */
final class Recommendations extends ArrayIterator {

  use JsonResourceParserTrait;

  /**
   * Creates a new Recommendations object from a resource—typically a file.
   *
   * @param resource $recommendations_resource
   *   Configuration to be parse; given as a PHP resource.
   * @return \Acquia\Cli\Command\App\From\Recommendation\Recommendations
   *   A defined set of *all possible* recommendations. These are not limited
   *   to any particular site.
   */
  public static function createFromResource($recommendations_resource): Recommendations {
    try {
      $parsed = static::parseJsonResource($recommendations_resource);
    }
    catch (Exception $e) {
      // Under any circumstance where the given resource is malformed, the
      // remainder of the script should still proceed. I.e. it's better to
      // produce a valid composer.json that has no recommendations than to fail
      // to create one at all.
      return new static([]);
    }
    $config_recommendations = $parsed['data'] ?? [];
    if (!is_array($config_recommendations)) {
      return new static([]);
    }
    return new static(array_filter(array_map(function ($config) {
      return DefinedRecommendation::createFromDefinition($config);
    }, $config_recommendations), function (RecommendationInterface $recommendation) {
      return !$recommendation instanceof NoRecommendation;
    }));
  }

}
