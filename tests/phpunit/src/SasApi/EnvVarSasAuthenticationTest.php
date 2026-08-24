<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\SasApi;

use Acquia\Cli\SasApi\SasCredentials;
use Acquia\Cli\Tests\TestBase;

class EnvVarSasAuthenticationTest extends TestBase
{
    private static string $sasBaseUri = 'https://sites-aggregation-service.dev.cicd.acquia.io/api';

    public function setUp(mixed $output = null): void
    {
        parent::setUp();
        $this->cloudCredentials = new SasCredentials($this->datastoreCloud);
        putenv('ACLI_KEY=' . self::$key);
        putenv('ACLI_SECRET=' . self::$secret);
        putenv('ACLI_SAS_BASE_URI=' . self::$sasBaseUri);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('ACLI_KEY');
        putenv('ACLI_SECRET');
        putenv('ACLI_SAS_BASE_URI');
    }

    public function testKeyAndSecret(): void
    {
        $this->removeMockCloudConfigFile();
        self::assertEquals(self::$key, $this->cloudCredentials->getCloudKey());
        self::assertEquals(self::$secret, $this->cloudCredentials->getCloudSecret());
        self::assertEquals(self::$sasBaseUri, $this->cloudCredentials->getBaseUri());
    }

    public function testDefaultBaseUri(): void
    {
        putenv('ACLI_SAS_BASE_URI');
        self::assertEquals(
            'https://sites-aggregation-service.prod.cicd.acquia.io/api',
            $this->cloudCredentials->getBaseUri()
        );
    }
}
