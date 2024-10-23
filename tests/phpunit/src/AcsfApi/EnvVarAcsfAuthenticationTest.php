<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\AcsfApi;

use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Tests\TestBase;

class EnvVarAcsfAuthenticationTest extends TestBase
{
    private static string $acsfCurrentFactoryUrl = 'https://www.test-something.com';

    public function setUp(mixed $output = null): void
    {
        parent::setUp();
        $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
        putenv('ACSF_USERNAME=' . self::$key);
        putenv('ACSF_KEY=' . self::$secret);
        putenv('ACSF_FACTORY_URI=' . self::$acsfCurrentFactoryUrl);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('ACSF_USERNAME');
        putenv('ACSF_KEY');
    }

    public function testKeyAndSecret(): void
    {
        $this->removeMockCloudConfigFile();
        self::assertEquals(self::$key, $this->cloudCredentials->getCloudKey());
        self::assertEquals(self::$secret, $this->cloudCredentials->getCloudSecret());
        self::assertEquals(self::$acsfCurrentFactoryUrl, $this->cloudCredentials->getBaseUri());
    }
}
