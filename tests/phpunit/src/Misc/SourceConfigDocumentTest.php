<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SourceConfigDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(SourceConfigDocument::class)]
class SourceConfigDocumentTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../../../fixtures/source-config';

    /**
     * @return array<string, string>
     */
    private static function expectedFiles(): array
    {
        $files = [];
        foreach ((new Finder())->files()->in(self::FIXTURE_DIR . '/expected')->name('*.yml') as $file) {
            // toFiles() always joins collection directories with '/'; Finder
            // uses the OS separator, which is '\' on Windows.
            $files[str_replace('\\', '/', $file->getRelativePathname())] = $file->getContents();
        }
        ksort($files);
        return $files;
    }

    public function testFixtureRoundTrips(): void
    {
        $document = file_get_contents(self::FIXTURE_DIR . '/document.yml');
        $files = SourceConfigDocument::toFiles($document);
        ksort($files);
        $this->assertSame(self::expectedFiles(), $files);
        $this->assertSame($document, SourceConfigDocument::fromFiles($files));
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function documents(): array
    {
        return [
            'collections become nested directories' => [
                "'':\n  system.site:\n    name: Site\nlanguage.de:\n  system.site:\n    name: Seite\nlanguage.nl:\n  system.site:\n    name: Website\n",
                [
                    'language/de/system.site.yml' => "name: Seite\n",
                    'language/nl/system.site.yml' => "name: Website\n",
                    'system.site.yml' => "name: Site\n",
                ],
            ],
            'default collection only' => [
                "'':\n  system.site:\n    name: Site\n",
                ['system.site.yml' => "name: Site\n"],
            ],
            'integer collection' => [
                "123:\n  a.b:\n    a: b\n",
                ['123/a.b.yml' => "a: b\n"],
            ],
            'integer name' => [
                "'':\n  123:\n    a: b\n",
                ['123.yml' => "a: b\n"],
            ],
            'name containing dot-dot' => [
                "'':\n  a..b:\n    a: b\n",
                ['a..b.yml' => "a: b\n"],
            ],
            'nesting deeper than ten levels stays in block style' => [
                "'':\n  a.b:\n    l1:\n      l2:\n        l3:\n          l4:\n            l5:\n              l6:\n                l7:\n                  l8:\n                    l9:\n                      l10:\n                        l11:\n                          - deep\n",
                ['a.b.yml' => "l1:\n  l2:\n    l3:\n      l4:\n        l5:\n          l6:\n            l7:\n              l8:\n                l9:\n                  l10:\n                    l11:\n                      - deep\n"],
            ],
            'scalars, sequences, empty maps and multi-line strings keep the core encoding' => [
                "'':\n  a.b:\n    nothing: null\n    flag: false\n    count: 3\n    ratio: 1.0\n    numeric: '123'\n    unicode: 'Ünïcødé ✓'\n    'key: colon': 1\n    list:\n      - a\n      - b\n    empty: {  }\n    text: |\n      line 1\n      line 2\n    text_no_newline: |-\n      line 1\n      line 2\n    tagged: !custom_tag value\n",
                ['a.b.yml' => "nothing: null\nflag: false\ncount: 3\nratio: 1.0\nnumeric: '123'\nunicode: 'Ünïcødé ✓'\n'key: colon': 1\nlist:\n  - a\n  - b\nempty: {  }\ntext: |\n  line 1\n  line 2\ntext_no_newline: |-\n  line 1\n  line 2\ntagged: !custom_tag value\n"],
            ],
        ];
    }

    /**
     * @param array<string, string> $files
     */
    #[DataProvider('documents')]
    public function testToFilesAndBack(string $document, array $files): void
    {
        $actual = SourceConfigDocument::toFiles($document);
        ksort($actual);
        $this->assertSame($files, $actual);
        $this->assertSame($document, SourceConfigDocument::fromFiles($files));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidDocuments(): array
    {
        $rows = [
            'empty' => ['', 'The configuration document is empty or not a map of collections.'],
            'empty map' => ['{}', 'The configuration document is empty or not a map of collections.'],
            'scalar' => ['foo', 'The configuration document is empty or not a map of collections.'],
            'scalar collection' => ["'': foo\n", 'Collection "" is not a map of configuration objects.'],
            'unsafe collection after a good one' => ["'':\n  a:\n    k: v\nlanguage/nl:\n  a:\n    k: v\n", '"language/nl" is not a valid collection name.'],
            'unsafe name after a good one' => ["'':\n  a:\n    k: v\n  ../x:\n    k: v\n", '"../x" is not a valid configuration name.'],
        ];
        foreach (['', '.', '..', 'a/b', 'a\\b', "a\0b", '../x'] as $name) {
            $rows['unsafe name ' . json_encode($name)] = [Yaml::dump(['' => [$name => ['k' => 'v']]]), sprintf('"%s" is not a valid configuration name.', $name)];
        }
        foreach (['..', 'language/nl', 'a..b', 'language.'] as $collection) {
            $rows['unsafe collection ' . json_encode($collection)] = [Yaml::dump([$collection => ['a' => ['k' => 'v']]]), sprintf('"%s" is not a valid collection name.', $collection)];
        }
        return $rows;
    }

    #[DataProvider('invalidDocuments')]
    public function testToFilesRejects(string $document, string $message): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage($message);
        SourceConfigDocument::toFiles($document);
    }

    public function testFromFilesSortsAndNormalizesSeparators(): void
    {
        $document = SourceConfigDocument::fromFiles(['language\\nl\\system.site.yml' => "name: Website\n", 'system.site.yml' => "name: Site\n", 'core.extension.yml' => "module: {  }\n"]);
        $this->assertSame("'':\n  core.extension:\n    module: {  }\n  system.site:\n    name: Site\nlanguage.nl:\n  system.site:\n    name: Website\n", $document);
    }

    public function testFromFilesNamesInvalidFile(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('language/nl/system.site.yml is not valid YAML: Malformed inline YAML string');
        SourceConfigDocument::fromFiles(['language/nl/system.site.yml' => "name: 'unterminated\n"]);
    }
}
