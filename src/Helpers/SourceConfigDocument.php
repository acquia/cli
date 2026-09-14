<?php

declare(strict_types=1);

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * Converts between a Source site's configuration document and .acquia/config.
 *
 * The document is one YAML map: collection => config name => values. The
 * default collection is keyed '' and maps to the directory root; any other
 * collection maps to a subdirectory with its dots turned into slashes
 * (language.nl => language/nl). Each config object is one <name>.yml file.
 * Files are encoded and decoded exactly like Drupal core does, so a pull
 * followed by a push reproduces the document byte for byte.
 */
final class SourceConfigDocument
{
    /**
     * @return array<string, string>
     *   Relative file path => file contents.
     */
    public static function toFiles(string $yaml): array
    {
        $document = self::decode($yaml);
        if (!is_array($document) || $document === []) {
            throw new AcquiaCliException('The configuration document is empty or not a map of collections.');
        }
        $files = [];
        foreach ($document as $collection => $objects) {
            if (!is_array($objects)) {
                throw new AcquiaCliException('Collection "{collection}" is not a map of configuration objects.', ['collection' => $collection]);
            }
            $dir = $collection === '' ? '' : str_replace('.', '/', self::pathSegment($collection)) . '/';
            foreach ($objects as $name => $values) {
                $files[$dir . self::pathSegment($name) . '.yml'] = self::encode($values);
            }
        }
        return $files;
    }

    /**
     * @param array<string, string> $files
     *   Relative file path => file contents.
     */
    public static function fromFiles(array $files): string
    {
        $document = [];
        foreach ($files as $path => $yaml) {
            $path = str_replace('\\', '/', $path);
            $dir = dirname($path);
            $collection = $dir === '.' ? '' : str_replace('/', '.', $dir);
            try {
                $document[$collection][basename($path, '.yml')] = self::decode($yaml);
            } catch (ParseException $e) {
                throw new AcquiaCliException('{file} is not valid YAML: {error}', ['file' => $path, 'error' => $e->getMessage()]);
            }
        }
        ksort($document);
        foreach ($document as &$objects) {
            ksort($objects);
        }
        return self::encode($document);
    }

    /**
     * Rejects collection and config names that could escape .acquia/config.
     */
    private static function pathSegment(string|int $segment): string
    {
        $segment = (string) $segment;
        if ($segment === '' || preg_match('#[/\\\\]|\.\.#', $segment)) {
            throw new AcquiaCliException('"{segment}" is not a valid configuration name.', ['segment' => $segment]);
        }
        return $segment;
    }

    private static function decode(string $yaml): mixed
    {
        return (new Parser())->parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_CUSTOM_TAGS);
    }

    private static function encode(mixed $data): string
    {
        return (new Dumper(2))->dump($data, PHP_INT_MAX, 0, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
