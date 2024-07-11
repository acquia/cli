<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App\From;

use Acquia\Cli\Command\App\From\Configuration;
use DomainException;
use Exception;
use JsonException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ConfigurationTest extends TestCase
{
    use ProphecyTrait;

    protected Configuration $sut;

    /**
     * @param string $configuration
     *   A JSON string from which to create a configuration object.
     * @dataProvider getTestConfigurations
     */
    public function test(string $configuration, Exception $expected_exception): void
    {
        $test_stream = fopen('php://memory', 'rw');
        fwrite($test_stream, $configuration);
        rewind($test_stream);
        $this->expectExceptionObject($expected_exception);
        Configuration::createFromResource($test_stream);
    }

    /**
     * @return array<mixed>
     */
    public function getTestConfigurations(): array
    {
        return [
            'bad JSON in configuration file' => ['{,}', new JsonException('Syntax error', JSON_ERROR_SYNTAX)],
            'empty configuration file' => [json_encode((object) []), new DomainException('Missing required key: rootPackageDefinition')],
        ];
    }
}
