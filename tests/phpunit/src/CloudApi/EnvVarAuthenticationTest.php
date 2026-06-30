<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\Tests\TestBase;

class EnvVarAuthenticationTest extends TestBase
{
    protected string $cloudApiBaseUri = 'https://www.acquia.com';

    public function setUp(mixed $output = null): void
    {
        parent::setUp();
        putenv('ACLI_KEY=' . self::$key);
        putenv('ACLI_SECRET=' . self::$secret);
        putenv('ACLI_CLOUD_API_BASE_URI=' . $this->cloudApiBaseUri);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('ACLI_KEY');
        putenv('ACLI_SECRET');
        putenv('ACLI_CLOUD_API_BASE_URI');
    }

    public function testKeyAndSecret(): void
    {
        $this->removeMockCloudConfigFile();
        self::assertEquals(self::$key, $this->cloudCredentials->getCloudKey());
        self::assertEquals(self::$secret, $this->cloudCredentials->getCloudSecret());
        self::assertEquals($this->cloudApiBaseUri, $this->cloudCredentials->getBaseUri());
    }

    public function testV3BaseUriFromEnvVar(): void
    {
        $v3Uri = 'https://gateway.dev.api.acquia.io/v3';
        putenv('ACLI_CLOUD_API_V3_BASE_URI=' . $v3Uri);
        self::assertEquals($v3Uri, $this->cloudCredentials->getV3BaseUri());
        putenv('ACLI_CLOUD_API_V3_BASE_URI');
        self::assertNull($this->cloudCredentials->getV3BaseUri());
    }
}
