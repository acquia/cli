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

        // Approach 1: Test with a mock that counts invocations
        $input1 = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $hasArgumentCallCount = 0;
        $input1->method('hasArgument')->willReturnCallback(function($param) use (&$hasArgumentCallCount) {
            $hasArgumentCallCount++;
            return false;
        });
        $input1->method('hasParameterOption')->willReturn(false);
        
        // Test with empty array - should use early return
        $postParamsProperty->setValue($command, []);
        $result1 = $hasJsonPostParams->invoke($command, $input1);
        $this->assertFalse($result1);
        $this->assertEquals(0, $hasArgumentCallCount, 'hasArgument should not be called with empty postParams - early return should prevent foreach execution');
        
        // Reset counter and test with non-empty array 
        $hasArgumentCallCount = 0;
        $postParamsProperty->setValue($command, ['test' => ['type' => 'string']]);
        $result1b = $hasJsonPostParams->invoke($command, $input1);
        $this->assertFalse($result1b);
        $this->assertGreaterThan(0, $hasArgumentCallCount, 'hasArgument should be called with non-empty postParams');

        // Approach 2: Use a spy pattern to detect execution flow
        $executionPath = [];
        $input2 = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input2->method('hasArgument')->willReturnCallback(function($param) use (&$executionPath) {
            $executionPath[] = "hasArgument($param)";
            return false;
        });
        $input2->method('hasParameterOption')->willReturnCallback(function($param) use (&$executionPath) {
            $executionPath[] = "hasParameterOption($param)";
            return false;
        });
        
        // Test empty postParams - execution path should be empty
        $executionPath = [];
        $postParamsProperty->setValue($command, []);
        $hasJsonPostParams->invoke($command, $input2);
        $this->assertEmpty($executionPath, 'No methods should be called when postParams is empty (early return)');
        
        // Test non-empty postParams - execution path should not be empty
        $executionPath = [];
        $postParamsProperty->setValue($command, ['param1' => ['type' => 'string']]);
        $hasJsonPostParams->invoke($command, $input2);
        $this->assertNotEmpty($executionPath, 'Methods should be called when postParams is not empty');

        // Case 3: Parameters exist with non-null value (should return true)
        $input3 = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input3->method('hasArgument')->willReturnMap([['param1', true], ['param2', false]]);
        $input3->method('getArgument')->with('param1')->willReturn('test-value');
        $input3->method('hasParameterOption')->willReturn(false);
        $postParamsProperty->setValue($command, ['param1' => ['type' => 'string'], 'param2' => ['type' => 'integer']]);
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

    /**
     * Additional test specifically designed to kill the ReturnRemoval mutation at line 445.
     * Uses a different strategy with controlled side effects.
     */
    public function testHasJsonPostParamsEarlyReturnMutation(): void
    {
        $command = $this->createCommand();
        
        $reflectionClass = new \ReflectionClass(ApiBaseCommand::class);
        $hasJsonPostParams = $reflectionClass->getMethod('hasJsonPostParams');
        $postParamsProperty = $reflectionClass->getProperty('postParams');
        
        // Strategy: Use a mock that tracks exact call sequences and throws on unexpected calls
        $sideEffectTracker = [];
        $input = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        
        // Configure mock to track all method calls with side effects
        $input->method('hasArgument')->willReturnCallback(function($param) use (&$sideEffectTracker) {
            $sideEffectTracker[] = "hasArgument:$param";
            return false;
        });
        
        $input->method('hasParameterOption')->willReturnCallback(function($param) use (&$sideEffectTracker) {
            $sideEffectTracker[] = "hasParameterOption:$param";
            return false;
        });
        
        // Test 1: Empty postParams should have NO side effects (early return should prevent foreach)
        $sideEffectTracker = [];
        $postParamsProperty->setValue($command, []);
        $result = $hasJsonPostParams->invoke($command, $input);
        
        $this->assertFalse($result);
        $this->assertCount(0, $sideEffectTracker, 'Early return should prevent any method calls when postParams is empty');
        
        // Test 2: Non-empty postParams should have side effects (foreach should execute)
        $sideEffectTracker = [];
        $postParamsProperty->setValue($command, ['test_param' => ['type' => 'string']]);
        $result = $hasJsonPostParams->invoke($command, $input);
        
        $this->assertFalse($result);
        $this->assertContains('hasArgument:test_param', $sideEffectTracker, 'Non-empty postParams should cause method calls');
        
        // Test 3: Verify the exact sequence of calls
        $sideEffectTracker = [];
        $postParamsProperty->setValue($command, [
            'param1' => ['type' => 'string'], 
            'param2' => ['type' => 'integer']
        ]);
        $hasJsonPostParams->invoke($command, $input);
        
        $this->assertContains('hasArgument:param1', $sideEffectTracker);
        $this->assertContains('hasArgument:param2', $sideEffectTracker);
    }

    /**
     * Fourth approach: Performance-based test to detect early return optimization
     */
    public function testHasJsonPostParamsPerformanceOptimization(): void
    {
        $command = $this->createCommand();
        
        $reflectionClass = new \ReflectionClass(ApiBaseCommand::class);
        $hasJsonPostParams = $reflectionClass->getMethod('hasJsonPostParams');
        $postParamsProperty = $reflectionClass->getProperty('postParams');
        
        // Create a slow mock - if early return works, we won't hit the slow operations
        $slowInput = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $operationCount = 0;
        
        $slowInput->method('hasArgument')->willReturnCallback(function($param) use (&$operationCount) {
            $operationCount++;
            // Simulate slow operation
            usleep(1000); // 1ms delay
            return false;
        });
        
        $slowInput->method('hasParameterOption')->willReturnCallback(function($param) use (&$operationCount) {
            $operationCount++;
            usleep(1000); // 1ms delay  
            return false;
        });
        
        // Test empty postParams - should be fast (early return)
        $startTime = microtime(true);
        $operationCount = 0;
        $postParamsProperty->setValue($command, []);
        $result = $hasJsonPostParams->invoke($command, $slowInput);
        $emptyParamsTime = microtime(true) - $startTime;
        
        $this->assertFalse($result);
        $this->assertEquals(0, $operationCount, 'No operations should occur with empty postParams');
        $this->assertLessThan(0.0005, $emptyParamsTime, 'Empty postParams should be very fast due to early return');
        
        // Test non-empty postParams - will be slower (foreach executes)
        $startTime = microtime(true);
        $operationCount = 0;
        $postParamsProperty->setValue($command, [
            'param1' => ['type' => 'string'],
            'param2' => ['type' => 'integer']
        ]);
        $result = $hasJsonPostParams->invoke($command, $slowInput);
        $nonEmptyParamsTime = microtime(true) - $startTime;
        
        $this->assertFalse($result);
        $this->assertGreaterThan(0, $operationCount, 'Operations should occur with non-empty postParams');
        $this->assertGreaterThan($emptyParamsTime, $nonEmptyParamsTime, 'Non-empty postParams should take longer');
    }

    /**
     * Final approach: Direct execution path verification using custom exception
     */
    public function testHasJsonPostParamsExecutionPath(): void
    {
        $command = $this->createCommand();
        
        $reflectionClass = new \ReflectionClass(ApiBaseCommand::class);
        $hasJsonPostParams = $reflectionClass->getMethod('hasJsonPostParams');
        $postParamsProperty = $reflectionClass->getProperty('postParams');
        
        // Create input that tracks execution via exception messages
        $pathTrackerInput = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        
        $pathTrackerInput->method('hasArgument')->willReturnCallback(function($param) {
            throw new \RuntimeException("Execution reached hasArgument($param) - early return failed!");
        });
        
        $pathTrackerInput->method('hasParameterOption')->willReturnCallback(function($param) {
            throw new \RuntimeException("Execution reached hasParameterOption($param) - early return failed!");
        });
        
        // Test 1: Empty postParams should NOT reach the exception (early return prevents it)
        $postParamsProperty->setValue($command, []);
        
        try {
            $result = $hasJsonPostParams->invoke($command, $pathTrackerInput);
            $this->assertFalse($result, 'Should return false via early return');
            $earlyReturnWorked = true;
        } catch (\RuntimeException $e) {
            $earlyReturnWorked = false;
            $this->fail('Early return did not work - execution reached foreach: ' . $e->getMessage());
        }
        
        $this->assertTrue($earlyReturnWorked, 'Early return should prevent foreach execution with empty postParams');
        
        // Test 2: Non-empty postParams SHOULD reach the exception (foreach executes)
        $postParamsProperty->setValue($command, ['test_param' => ['type' => 'string']]);
        
        $expectedException = false;
        try {
            $hasJsonPostParams->invoke($command, $pathTrackerInput);
        } catch (\RuntimeException $e) {
            $expectedException = true;
            $this->assertStringContainsString('hasArgument(test_param)', $e->getMessage(), 
                'Should reach hasArgument call in foreach loop with non-empty postParams');
        }
        
        $this->assertTrue($expectedException, 'Non-empty postParams should cause foreach execution and throw exception');
    }
}
