<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App\From;

use Acquia\Cli\Command\App\From\Recommendation\RecommendationInterface;
use Acquia\Cli\Command\App\From\Recommendation\Recommendations;
use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class RecommendationsTest extends TestCase
{
    use ProphecyTrait;

    protected const NO_RECOMMENDATIONS = [];

    /**
     * @param string $configuration
     *   A JSON string from which to create a configuration object.
     * @param \Acquia\Cli\Command\App\From\Recommendation\RecommendationInterface|\JsonException $expectation
     *   An expected recommendation or a JSON exception in the case that the given
     *   $configuration is malformed.
     * @dataProvider getTestConfigurations
     */
    public function test(string $configuration, mixed $expectation): void
    {
        $test_stream = fopen('php://memory', 'rw');
        fwrite($test_stream, $configuration);
        rewind($test_stream);
        $recommendations = Recommendations::createFromResource($test_stream);
        if ($expectation === static::NO_RECOMMENDATIONS) {
            $this->assertEmpty($recommendations);
        } else {
            assert($expectation instanceof RecommendationInterface);
            $this->assertNotEmpty($recommendations);
            $extension_prophecy = $this->prophesize(ExtensionInterface::class);
            $extension_prophecy->getName()->willReturn('foo');
            $mock_extension = $extension_prophecy->reveal();
            $actual_recommendation = $recommendations->current();
            $this->assertSame($expectation->applies($mock_extension), $actual_recommendation->applies($mock_extension));
            $this->assertSame($expectation->getPackageName(), $actual_recommendation->getPackageName());
            $this->assertSame($expectation->getVersionConstraint(), $actual_recommendation->getVersionConstraint());
        }
    }

    /**
     * @return array<mixed>
     */
    public function getTestConfigurations(): array
    {
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        return [
        'bad JSON in configuration file' => [
        '{,}',
        static::NO_RECOMMENDATIONS,
        ],
        'empty configuration file' => [
        json_encode((object) []),
        static::NO_RECOMMENDATIONS,
        ],
        'unexpected recommendations value' => [
        json_encode(['data' => true]),
        static::NO_RECOMMENDATIONS,
        ],
        'empty recommendations key' => [
        json_encode(['data' => []]),
        static::NO_RECOMMENDATIONS,
        ],
        'populated recommendations key with invalid item' => [
        json_encode(['recommendations' => [[]]]),
        static::NO_RECOMMENDATIONS,
        ],
        'populated recommendations key with valid item' => [
        json_encode([
        'data' => [
        [
        'package' => 'foo',
        'constraint' => '^1.42',
        'replaces' => [
        'name' => 'foo',
        ],
        ],
        ],
        ]),
        new TestRecommendation(true, 'foo', '^1.42'),
        ],
        ];
        // phpcs:enable
    }
}
