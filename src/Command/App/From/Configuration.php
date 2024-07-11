<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From;

use Acquia\Cli\Command\App\From\Safety\ArrayValidationTrait;

/**
 * Represents contextual and environmental information.
 */
final class Configuration
{
    use ArrayValidationTrait;
    use JsonResourceParserTrait;

    /**
     * The current configuration, usually parsed from a file.
     *
     * @var array<mixed>
     */
    protected array $array;

    /**
     * Configuration constructor.
     *
     * @param array $config
     *   An array of configuration, usually parsed from a configuration file.
     */
    protected function __construct(array $config)
    {
        $this->array = static::schema([
            'rootPackageDefinition' => 'is_array',
        ])($config);
    }

    /**
     * Creates a configuration object from configuration given as a PHP
     * resource.
     *
     * The given PHP resource is usually obtained by calling fopen($location).
     *
     * @param resource $configuration_resource
     *   Configuration to be parse; given as a PHP resource.
     * @return \Acquia\Cli\Command\App\From\Configuration
     *   A new configuration object.
     */
    public static function createFromResource($configuration_resource): Configuration
    {
        return new static(static::parseJsonResource($configuration_resource));
    }

    /**
     * Gets an basic root composer package definition for a Drupal 9+ project.
     *
     * @return array<mixed>
     *   An array representing a root composer package definition. From this
     *   starting point, additional dependencies and metadata can be added until
     *   an acceptable project is defined for migrating a source site to Drupal
     *   9+.
     */
    public function getRootPackageDefinition(): array
    {
        return $this->array['rootPackageDefinition'];
    }
}
