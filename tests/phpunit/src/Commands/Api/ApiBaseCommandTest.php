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
    public function testCastObjectHandlesInvalidJson(): void
    {
        $method = (new \ReflectionClass($this->command))->getMethod('castObject');

        $invalidJson = '{invalid:"json"}';
        $result = $method->invoke($this->command, $invalidJson);
        $this->assertSame($invalidJson, $result);
    }

    public function testCastObjectHandlesArray(): void
    {
        $method = (new \ReflectionClass($this->command))->getMethod('castObject');

        $input = ['key' => 'value'];
        $result = $method->invoke($this->command, $input);
        $this->assertIsObject($result);
    }

    private function generateDeepJson(int $depth): string
    {
        $data = '"end"';
        for ($i = 0; $i < $depth; $i++) {
            $data = '{"level":' . $data . '}';
        }
        return $data;
    }
    public function testCastObjectJsonDepthLimits(): void
    {
        $method = (new \ReflectionClass($this->command))->getMethod('castObject');

        // Generate JSON within safe depth (<=512)
        $validJson = $this->generateDeepJson(511);
        $result = $method->invoke($this->command, $validJson);
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('level', $result);

        // Generate JSON that exceeds depth (513)
        $tooDeepJson = $this->generateDeepJson(513);
        $result = $method->invoke($this->command, $tooDeepJson);

        // Since castObject swallows JsonException and returns original string,
        // verify fallback works.
        $this->assertIsString($result, 'Should return original string if JSON depth exceeded');
        $this->assertStringStartsWith('{', $result);
    }
}
