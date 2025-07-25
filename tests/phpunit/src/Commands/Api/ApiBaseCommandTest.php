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
}
