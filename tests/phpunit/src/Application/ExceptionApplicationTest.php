<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Tests\ApplicationTestBase;

/**
 * Tests exceptions rewritten by the Symfony Event Dispatcher.
 *
 * These must be tested using the ApplicationTestBase, since the Symfony
 * CommandTester does not fire Event Dispatchers.
 *
 * This test suite only needs to verify that the listener catches at least one
 * exception. Specific exceptions are tested in ExceptionListenerTest.
 */
class ExceptionApplicationTest extends ApplicationTestBase
{
    /**
     * @group serial
     */
    public function testInvalidApiCredentials(): void
    {
        $this->setInput([
        'applicationUuid' => '2ed281d4-9dec-4cc3-ac63-691c3ba002c2',
        'command' => 'aliases',
        ]);
        $this->mockUnauthorizedRequest();
        $buffer = $this->runApp();
        // This is sensitive to the display width of the test environment, so that's fun.
        self::assertStringContainsString('Your Cloud Platform API credentials are invalid.', $buffer);
    }
}
