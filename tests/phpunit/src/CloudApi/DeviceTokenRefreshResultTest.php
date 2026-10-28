<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\DeviceTokenRefreshResult;
use PHPUnit\Framework\TestCase;

/**
 * The refresher's own tests are in the serial group, which Infection excludes,
 * so the three outcomes are pinned here where they are always exercised.
 */
class DeviceTokenRefreshResultTest extends TestCase
{
    public function testATokenCarriesNoFailure(): void
    {
        $result = DeviceTokenRefreshResult::token('an-access-token');

        $this->assertSame('an-access-token', $result->accessToken);
        // A token in hand cannot also mean the server was unreachable.
        $this->assertFalse($result->serverUnreachable);
    }

    public function testAnEndedSessionIsNotRetryable(): void
    {
        $result = DeviceTokenRefreshResult::sessionEnded();

        $this->assertNull($result->accessToken);
        $this->assertFalse($result->serverUnreachable);
    }

    public function testAnUnreachableServerIsDistinctFromAnEndedSession(): void
    {
        $result = DeviceTokenRefreshResult::serverUnreachable();

        $this->assertNull($result->accessToken);
        $this->assertTrue($result->serverUnreachable);
    }
}
