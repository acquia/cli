<?php

declare(strict_types=1);

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Finder\Finder;
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
 * (language.nl => language/nl). Each config object is one <name>.yml file,
 * exactly like \Drupal\Core\Config\FileStorage stores it on disk. Files are
 * encoded and decoded exactly like Drupal core does, so a pull followed by a
 * push reproduces the document byte for byte.
 *
 * @see \Drupal\Core\Config\FileStorage
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
            $dir = $collection === '' ? '' : str_replace('.', '/', self::assertPathIsSafe('collection', $collection, explode('.', $collection))) . '/';
            foreach ($objects as $name => $values) {
                $name = (string) $name;
                $files[$dir . self::assertPathIsSafe('configuration', $name, [$name]) . '.yml'] = self::encode($values);
            }
        }
        // Every path was validated above, so a bad document yields no files at all.
        return $files;
    }

    /**
     * Reads every *.yml file under $dir and encodes it as a document.
     */
    public static function fromDirectory(string $dir): string
    {
        $files = [];
        foreach ((new Finder())->files()->in($dir)->name('*.yml') as $file) {
            $files[$file->getRelativePathname()] = $file->getContents();
        }
        return self::fromFiles($files);
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
     * @param string $what
     *   What $name is, for the exception message: 'collection' or 'configuration'.
     * @param string $name
     *   The collection or configuration name as given in the document.
     * @param list<string> $segments
     *   The path segments the name becomes; a segment must not be '', '.' or
     *   '..' nor contain a slash, backslash or NUL. "a..b" is a legal segment.
     */
    private static function assertPathIsSafe(string $what, string $name, array $segments): string
    {
        foreach ($segments as $segment) {
            if (in_array($segment, ['', '.', '..'], true) || preg_match('#[/\\\\\0]#', $segment)) {
                throw new AcquiaCliException('"{name}" is not a valid {what} name.', ['name' => $name, 'what' => $what]);
            }
        }
        return $name;
    }

    /**
     * Deliberately not \Drupal\Component\Serialization\Yaml::decode()'s flags:
     *
     * - Yaml::PARSE_CUSTOM_TAGS: added to that method for services.yml's
     *   !tagged_iterator (https://www.drupal.org/node/3436859), unrelated to
     *   config.
     * - Yaml::PARSE_CONSTANT: that method could reasonably add this too, for
     *   !php/const/!php/enum in config and service YAML
     *   (https://www.drupal.org/node/3403883). Adding it here would be
     *   unsafe: such a tag would name a class from the Source site's own
     *   Drupal codebase, never autoloadable from acli's standalone process,
     *   so it could only fail or resolve to an unrelated value from this
     *   machine.
     *
     * Neither tag can appear in what this tool actually pulls: the export
     * this reads is built from plain config storage reads plus three literal
     * system.site strings, never a tagged or enum value. Parsing either tag
     * without its flag throws a clear ParseException, which is correct for
     * config that should never contain one.
     *
     * @see \Drupal\Core\Config\FileStorage::decode()
     * @see \Drupal\Component\Serialization\Yaml::decode()
     */
    private static function decode(string $yaml): mixed
    {
        return (new Parser())->parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
    }

    /**
     * @see \Drupal\Core\Config\FileStorage::encode()
     * @see \Drupal\Component\Serialization\Yaml::encode()
     */
    private static function encode(mixed $data): string
    {
        return (new Dumper(2))->dump($data, PHP_INT_MAX, 0, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
