<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App\From;

use Acquia\Cli\Command\App\From\Recommendation\AbandonmentRecommendation;
use Acquia\Cli\Command\App\From\Recommendation\DefinedRecommendation;
use Acquia\Cli\Command\App\From\Recommendation\NoRecommendation;
use Acquia\Cli\Command\App\From\Recommendation\RecommendationInterface;
use Acquia\Cli\Command\App\From\Recommendation\UniversalRecommendation;
use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class DefinedRecommendationTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @dataProvider getTestConfigurations
     */
    public function test(mixed $configuration, RecommendationInterface $expected): void
    {
        $actual = DefinedRecommendation::createFromDefinition($configuration);
        if ($expected instanceof NoRecommendation) {
            $this->assertInstanceOf(NoRecommendation::class, $actual);
        } else {
            $this->assertInstanceOf(RecommendationInterface::class, $actual);
            $this->assertNotInstanceOf(NoRecommendation::class, $actual);
            $extension_prophecy = $this->prophesize(ExtensionInterface::class);
            $extension_prophecy->getName()->willReturn('bar');
            $mock_extension = $extension_prophecy->reveal();
            if (!$actual instanceof UniversalRecommendation) {
                $this->assertSame($expected->applies($mock_extension), $actual->applies($mock_extension));
            }
            if ($expected->getPackageName() === TestRecommendation::ABANDON) {
                $this->assertInstanceOf(AbandonmentRecommendation::class, $actual);
            } else {
                $this->assertSame($expected->getPackageName(), $actual->getPackageName());
                $this->assertSame($expected->getVersionConstraint(), $actual->getVersionConstraint());
                $this->assertSame($expected->getPatches(), $actual->getPatches());
            }
        }
    }

    /**
     * @return array<mixed>
     */
    public static function getTestConfigurations(): array
    {
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        return [
            'config is not array' => [42, new NoRecommendation()],
            'empty array' => [[], new NoRecommendation()],
            'missing required key' => [
                ['package' => '', 'constraint' => ''],
                new NoRecommendation(),
            ],
            'key value does not match schema' => [
                [
                    'package' => 42,
                    'constraint' => '',
                    'replaces' => ['name' => ''],
                ],
                new NoRecommendation(),
            ],
            'nested key value does not match schema' => [
                [
                    'package' => '',
                    'constraint' => '',
                    'replaces' => ['name' => 42],
                ],
                new NoRecommendation(),
            ],
            'invalid patches key' => [
                [
                    'package' => 'foo',
                    'constraint' => '^1.42',
                    'patches' => [
                        0 => 'https://example.com',
                    ],
                    'replaces' => [
                        'name' => 'foo',
                    ],
                ],
                new NoRecommendation(),
            ],
            'invalid patches key value' => [
                [
                    'package' => 'foo',
                    'constraint' => '^1.42',
                    'patches' => [
                        'A patch description' => true,
                    ],
                    'replaces' => [
                        'name' => 'foo',
                    ],
                ],
                new NoRecommendation(),
            ],
            'missing replaces key, not universal by default' => [
                [
                    'package' => 'foo',
                    'constraint' => '^1.42',
                ],
                new NoRecommendation(),
            ],
            'missing replaces key, explicitly not universal' => [
                [
                    'universal' => false,
                    'package' => 'foo',
                    'constraint' => '^1.42',
                ],
                new NoRecommendation(),
            ],
            'valid config; does not apply' => [
                [
                    'package' => 'foo',
                    'constraint' => '^1.42',
                    'replaces' => [
                        'name' => 'foo',
                    ],
                ],
                new TestRecommendation(false, 'foo', '^1.42'),
            ],
            'valid config; does apply; missing replaces key but universal is true' => [
                [
                    'universal' => true,
                    'package' => 'foo',
                    'constraint' => '^1.42',
                ],
                new TestRecommendation(true, 'foo', '^1.42'),
            ],
            'valid config; does apply; no patches key' => [
                [
                    'package' => 'foo',
                    'constraint' => '^1.42',
                    'replaces' => [
                        'name' => 'bar',
                    ],
                ],
                new TestRecommendation(true, 'foo', '^1.42'),
            ],
            'valid config; does apply; empty patches value' => [
                [
                    'package' => 'foo',
                    'constraint' => '^1.42',
                    'patches' => [],
                    'replaces' => [
                        'name' => 'bar',
                    ],
                ],
                new TestRecommendation(true, 'foo', '^1.42'),
            ],
            'valid config; does apply; has patches' => [
                [
                    'package' => 'foo',
                    'constraint' => '^1.42',
                    'patches' => [
                        'A patch description' => 'https://example.com/example.patch',
                    ],
                    'install' => ['foo'],
                    'replaces' => [
                        'name' => 'bar',
                    ],
                ],
                new TestRecommendation(true, 'foo', '^1.42', ['foo'], false, [
                    'A patch description' => 'https://example.com/example.patch',
                ]),
            ],
            'valid config; does apply; has null package property' => [
                [
                    'package' => null,
                    'note' => 'Example: The module bar is no longer required because its functionality has been incorporated into Drupal core.',
                    'replaces' => [
                        'name' => 'bar',
                    ],
                    'vetted' => true,
                ],
                new TestRecommendation(true, TestRecommendation::ABANDON),
            ],
        ];
        // phpcs:enable
    }
}
