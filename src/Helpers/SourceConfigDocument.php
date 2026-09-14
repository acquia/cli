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
            $collection = (string) $collection;
            if (!is_array($objects)) {
                throw new AcquiaCliException('Collection "{collection}" is not a map of configuration objects.', ['collection' => $collection]);
            }
            $dir = $collection === '' ? '' : str_replace('.', '/', self::assertSafe('collection', $collection, explode('.', $collection))) . '/';
            foreach ($objects as $name => $values) {
                $name = (string) $name;
                $files[$dir . self::assertSafe('configuration', $name, [$name]) . '.yml'] = self::encode($values);
            }
        }
        // Every path was validated above, so a bad document yields no files at all.
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
     * Rejects a collection or config name that could escape .acquia/config.
     *
     * @param list<string> $segments
     *   The path segments the name becomes; a segment must not be '', '.' or
     *   '..' nor contain a slash, backslash or NUL. "a..b" is a legal segment.
     */
    private static function assertSafe(string $what, string $name, array $segments): string
    {
        foreach ($segments as $segment) {
            if (in_array($segment, ['', '.', '..'], true) || preg_match('#[/\\\\\0]#', $segment)) {
                throw new AcquiaCliException('"{name}" is not a valid {what} name.', ['name' => $name, 'what' => $what]);
            }
        }
        return $name;
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
