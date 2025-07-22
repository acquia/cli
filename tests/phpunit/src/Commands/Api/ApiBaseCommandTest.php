<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use ReflectionClass;

class ApiBaseCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(ApiBaseCommand::class);
    }

    public function testApiBaseCommand(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('api:base is not a valid command');
        $this->executeCommand();
    }

    /**
     * @dataProvider parseArrayValueProvider
     */
    public function testParseArrayValue(string|array $input, array $expected): void
    {
        $command = $this->createCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('parseArrayValue');

        $result = $method->invoke($command, $input);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array<string, array{string|array<mixed>, array<mixed>}>
     */
    public static function parseArrayValueProvider(): array
    {
        return [
            'Comma-separated string' => [
                'item1,item2,item3',
                ['item1', 'item2', 'item3'],
            ],
            'Empty array input' => [
                [],
                [],
            ],
            'Empty string' => [
                '',
                [''],
            ],
            'Existing array input' => [
                ['already', 'an', 'array'],
                ['already', 'an', 'array'],
            ],
            'JSON array of objects' => [
                '[{"environment_id":"164572-6ecd5987-5745-48b0-8cd3-397f5963aea2"},{"environment_id":"164548-97cc5820-b6b3-42f6-84b1-0cb81ac781d1"}]',
                [
                    ['environment_id' => '164572-6ecd5987-5745-48b0-8cd3-397f5963aea2'],
                    ['environment_id' => '164548-97cc5820-b6b3-42f6-84b1-0cb81ac781d1'],
                ],
            ],
            'JSON array of strings' => [
                '["item1","item2","item3"]',
                ['item1', 'item2', 'item3'],
            ],
            'JSON array with whitespace' => [
                '  [ "item1" , "item2" ]  ',
                ['item1', 'item2'],
            ],
            'JSON comma-separated objects' => [
                '{"environment_id":"164572-6ecd5987-5745-48b0-8cd3-397f5963aea2"},{"environment_id":"164548-97cc5820-b6b3-42f6-84b1-0cb81ac781d1"}',
                [
                    ['environment_id' => '164572-6ecd5987-5745-48b0-8cd3-397f5963aea2'],
                    ['environment_id' => '164548-97cc5820-b6b3-42f6-84b1-0cb81ac781d1'],
                ],
            ],
            'Single item string' => [
                'single-item',
                ['single-item'],
            ],
            'Single JSON object' => [
                '{"environment_id":"164572-6ecd5987-5745-48b0-8cd3-397f5963aea2"}',
                ['environment_id' => '164572-6ecd5987-5745-48b0-8cd3-397f5963aea2'],
            ],
        ];
    }

    /**
     * Test that doCastParamType handles object type correctly when value is already an array
     */
    public function testDoCastParamTypeWithObjectAndArrayValue(): void
    {
        $command = $this->createCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('doCastParamType');

        // Test that passing an array to object type doesn't cause TypeError.
        $result = $method->invoke($command, 'object', ['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $result);

        // Test that passing a string to object type works as expected.
        $result = $method->invoke($command, 'object', '{"key":"value"}');
        $this->assertEquals((object)['key' => 'value'], $result);
    }

    /**
     * @dataProvider shouldTreatAsArrayProvider
     */
    public function testShouldTreatAsArray(mixed $input, bool $expected): void
    {
        $command = $this->createCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('shouldTreatAsArray');

        $result = $method->invoke($command, $input);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function shouldTreatAsArrayProvider(): array
    {
        return [
            'Already an array' => [['item1', 'item2'], true],
            'Comma-separated JSON objects' => ['{"environment_id":"ABC"},{"environment_id":"DEF"}', true],
            'Comma-separated string' => ['item1,item2,item3', true],
            'Empty string' => ['', false],
            'JSON array' => ['[{"key":"value"}]', true],
            'JSON array of objects' => ['[{"environment_id":"ABC"},{"environment_id":"DEF"}]', true],
            'JSON object' => ['{"key":"value"}', true],
            'JSON with whitespace' => ['  [{"key":"value"}]  ', true],
            'Non-array non-string' => [123, false],
            'Not an array value' => ['just-a-string', false],
            'Object with whitespace' => ['  {"key":"value"}  ', true],
            'String without commas' => ['single-item', false],
        ];
    }
}
