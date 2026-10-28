<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\AcsfApi;

use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Tests\TestBase;

class AcsfCredentialsTest extends TestBase
{
    public function testHasNoDeviceAccessToken(): void
    {
        // ACSF authenticates with its own factory credentials; the device code
        // flow is Cloud Platform only.
        $credentials = new AcsfCredentials($this->datastoreCloud);

        $this->assertNull($credentials->getCloudDeviceAccessToken());
    }
}
