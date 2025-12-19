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

    /**
     * Tests the isBinaryParam method with different parameter specifications.
     *
     * @throws \ReflectionException
     */
    public function testIsBinaryParam(): void
    {
        $command = $this->createCommand();

        // Use reflection to access private method.
        $reflectionClass = new \ReflectionClass(ApiBaseCommand::class);
        $isBinaryParam = $reflectionClass->getMethod('isBinaryParam');

        // Case 1: null parameter spec (should return false)
        $result1 = $isBinaryParam->invoke($command, null);
        $this->assertFalse($result1, 'Null paramSpec should return false');

        // Case 2: parameter spec without format key (should return false)
        $result2 = $isBinaryParam->invoke($command, ['type' => 'string']);
        $this->assertFalse($result2, 'ParamSpec without format should return false');

        // Case 3: parameter spec with non-binary format (should return false)
        $result3 = $isBinaryParam->invoke($command, ['type' => 'string', 'format' => 'text']);
        $this->assertFalse($result3, 'Non-binary format should return false');

        // Case 4: parameter spec with binary format (should return true)
        $result4 = $isBinaryParam->invoke($command, ['type' => 'string', 'format' => 'binary']);
        $this->assertTrue($result4, 'Binary format should return true');
    }

    /**
     * Tests the hasJsonPostParams method with different scenarios.
     * This test specifically targets the ReturnRemoval mutation.
     *
     * @throws \ReflectionException
     */
    public function testHasJsonPostParams(): void
    {
        $command = $this->createCommand();

        // Use reflection to access private methods and properties.
        $reflectionClass = new \ReflectionClass(ApiBaseCommand::class);
        $hasJsonPostParams = $reflectionClass->getMethod('hasJsonPostParams');
        $postParamsProperty = $reflectionClass->getProperty('postParams');
        $postParamsProperty->setAccessible(true);

        // Case 1: Empty post parameters array (should return false immediately)
        $input1 = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input1->method('hasArgument')->willReturn(false);
        $input1->method('hasParameterOption')->willReturn(false);
        $postParamsProperty->setValue($command, []);
        $result1 = $hasJsonPostParams->invoke($command, $input1);
        $this->assertFalse($result1, 'Should return false when postParams is empty array');

        // Case 2: Parameters exist but all values are null (should return false after foreach loop)
        $input2 = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input2->method('hasArgument')->willReturn(false);
        $input2->method('hasParameterOption')->willReturn(false);
        $postParamsProperty->setValue($command, ['param1' => ['type' => 'string'], 'param2' => ['type' => 'integer']]);
        $result2 = $hasJsonPostParams->invoke($command, $input2);
        $this->assertFalse($result2, 'Should return false when all param values are null');

        // Case 3: Parameters exist with non-null value (should return true)
        $input3 = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input3->method('hasArgument')->willReturnMap([['param1', true], ['param2', false]]);
        $input3->method('getArgument')->with('param1')->willReturn('test-value');
        $input3->method('hasParameterOption')->willReturn(false);
        $result3 = $hasJsonPostParams->invoke($command, $input3);
        $this->assertTrue($result3, 'Should return true when a non-binary param has value');

        // Case 4: Only binary parameters have values (should return false)
        $input4 = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input4->method('hasArgument')->with('file_param')->willReturn(true);
        $input4->method('getArgument')->with('file_param')->willReturn('/path/to/file');
        $input4->method('hasParameterOption')->willReturn(false);
        $postParamsProperty->setValue($command, ['file_param' => ['type' => 'string', 'format' => 'binary']]);
        $result4 = $hasJsonPostParams->invoke($command, $input4);
        $this->assertFalse($result4, 'Should return false when only binary params have values');

        // Case 5: Mixed params - binary and non-binary, both with values (should return true for non-binary)
        $input5 = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input5->method('hasArgument')->willReturnMap([['file_param', true], ['json_param', true]]);
        $input5->method('getArgument')->willReturnMap([['file_param', '/path/to/file'], ['json_param', 'json-value']]);
        $input5->method('hasParameterOption')->willReturn(false);
        $postParamsProperty->setValue($command, [
            'file_param' => ['type' => 'string', 'format' => 'binary'],
            'json_param' => ['type' => 'string'],
        ]);
        $result5 = $hasJsonPostParams->invoke($command, $input5);
        $this->assertTrue($result5, 'Should return true when mixed params include non-binary with value');
    }
}
