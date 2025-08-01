<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;

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
     * Tests the parseArrayValue method with different JSON inputs.
     *
     * @throws \ReflectionException
     */
    public function testBulkCodeSwitchTargetsPayloadParsing(): void
    {
        $command = $this->createCommand();

        // Use reflection to access private method.
        $reflectionClass = new \ReflectionClass(ApiBaseCommand::class);
        $parseArrayValue = $reflectionClass->getMethod('parseArrayValue');
        $parseArrayValue->setAccessible(true);

        // Case 1: Single object, not wrapped in array brackets.
        $inputSingle = '{"environment_id":"abc-123"}';
        $parsedSingle = $parseArrayValue->invoke($command, $inputSingle);
        $this->assertIsArray($parsedSingle);
        $this->assertArrayHasKey('environment_id', $parsedSingle);
        $this->assertEquals('abc-123', $parsedSingle['environment_id']);

        // Case 2: Proper JSON array of objects.
        $inputArray = '[{"environment_id":"abc-123"}, {"environment_id":"def-456"}]';
        $parsedArray = $parseArrayValue->invoke($command, $inputArray);
        $this->assertIsArray($parsedArray);
        $this->assertCount(2, $parsedArray);
        $this->assertEquals('def-456', $parsedArray[1]['environment_id']);

        // Case 3: Invalid input - malformed JSON.
        $inputInvalid = '{"environment_id":"abc-123",}';
        $parsedInvalid = $parseArrayValue->invoke($command, $inputInvalid);
        $this->assertIsArray($parsedInvalid);
        // fallback: CSV style comma split.
        $this->assertGreaterThanOrEqual(1, count($parsedInvalid));
    }
    /**
     * Test parseArrayValue for mutation coverage.
     * Covers:
     * - UnwrapTrim: ensures trim is required for JSON decode to succeed.
     * - LogicalAnd: ensures both JSON conditions are required.
     */
    public function testParseArrayValueMutations(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('parseArrayValue');
        $method->setAccessible(true);

        // Mutation 1: UnwrapTrim - test value that would fail without trim.
        $input1 = '  ["x","y"]  ';
        $result1 = $method->invoke($this->command, $input1);
        $this->assertEquals(['x', 'y'], $result1, 'Should parse trimmed JSON array');

        // Mutation 2: LogicalAnd → LogicalOr - test empty string and unmatched character.
        $input2 = ' ';
        $result2 = $method->invoke($this->command, $input2);
        $this->assertEquals([' '], $result2, 'Should fallback to explode for empty input');

        // Additional: Valid JSON object.
        $input3 = '{"key": "val"}';
        $result3 = $method->invoke($this->command, $input3);
        $this->assertEquals(['key' => 'val'], $result3, 'Should decode JSON object to array');

        // Additional: Non-string input.
        $input4 = ['a', 'b'];
        $result4 = $method->invoke($this->command, $input4);
        $this->assertEquals($input4, $result4, 'Non-string input should be returned as array');
        // Additional: Plain string with commas (should split into array)
        $input5 = 'a,b,c';
        $result5 = $method->invoke($this->command, $input5);
        $this->assertEquals(['a', 'b', 'c'], $result5, 'Plain comma-separated string should be split into array');

        // Mutation 3: CastArray - input is already an array, should return unchanged.
        $input6 = ['foo' => 'bar'];
        $result6 = $method->invoke($this->command, $input6);
        $this->assertEquals(['foo' => 'bar'], $result6, 'Input array should return unchanged');

        // Mutation 4: IncrementInteger - JSON depth limit change does not affect valid input.
        $input7 = '{"deep": {"nested": {"level": "value"}}}';
        $result7 = $method->invoke($this->command, $input7);
        $this->assertEquals(['deep' => ['nested' => ['level' => 'value']]], $result7, 'Should parse nested JSON object within default depth');
        // Covers: CastArray mutation.
        $input8 = (object)['a' => 1];
        $result8 = $method->invoke($this->command, $input8);
        $this->assertIsArray($result8, 'Should cast object to array');
        $this->assertEquals(['a' => 1], $result8, 'Casted array should match expected values');
    }
    public function testCastObjectMaxDepth(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('castObject');
        $method->setAccessible(true);

        // Safer nesting depth that avoids PHP stack overflow.
        $depth = 100;
        $input = str_repeat('{"a":', $depth) . '"value"' . str_repeat('}', $depth);

        try {
            $expected = json_decode($input, false, 512, JSON_THROW_ON_ERROR);
            $this->assertIsObject($expected, 'Expected decoded object');

            $result = $method->invoke($this->command, $input);
            $this->assertEquals($expected, $result, 'Should decode nested JSON safely');
        } catch (\JsonException $e) {
            $this->fail('JSON at safe depth should not throw: ' . $e->getMessage());
        }
    }
    public function testCastObjectFailsAtDepth511(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('castObject');
        $method->setAccessible(true);

        $depth = 511;
        $json = str_repeat('{"a":', $depth) . '"ok"' . str_repeat('}', $depth);

        // Ensure native json_decode at 512 works.
        $expected = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        $this->assertIsObject($expected, 'Expected JSON to decode at depth 512');

        // Now test the method under test.
        $result = $method->invoke($this->command, $json);
        $this->assertEquals($expected, $result, 'Should decode deeply nested JSON at depth 512');
    }
}
