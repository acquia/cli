<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\OktaConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('serial')]
class OktaConfigTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $savedEnvVars = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ACLI_DEVICE_CLIENT_ID', 'ACLI_OKTA_DOMAIN', 'ACLI_OKTA_AUTH_SERVER_ID'] as $var) {
            $this->savedEnvVars[$var] = getenv($var);
            putenv($var);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnvVars as $var => $value) {
            putenv($value === false ? $var : $var . '=' . $value);
        }
        parent::tearDown();
    }

    public function testResolvesValuesFromTheEnvironment(): void
    {
        putenv('ACLI_DEVICE_CLIENT_ID=env-client');
        putenv('ACLI_OKTA_DOMAIN=env.okta.com');
        putenv('ACLI_OKTA_AUTH_SERVER_ID=ausEnv');
        $config = new OktaConfig();

        $this->assertSame('env-client', $config->clientId());
        $this->assertSame('env.okta.com', $config->domain());
        $this->assertSame('ausEnv', $config->authServerId());
    }

    public function testReturnsEmptyStringsWhenNothingIsSet(): void
    {
        $config = new OktaConfig();

        $this->assertSame('', $config->clientId());
        $this->assertSame('', $config->domain());
        $this->assertSame('', $config->authServerId());
    }

    public function testIsConfiguredIsFalseWhenNothingIsSet(): void
    {
        $this->assertFalse((new OktaConfig())->isConfigured());
    }

    public function testIsConfiguredIsFalseWhenOnlySomeValuesAreSet(): void
    {
        putenv('ACLI_DEVICE_CLIENT_ID=env-client');
        putenv('ACLI_OKTA_DOMAIN=env.okta.com');

        $this->assertFalse((new OktaConfig())->isConfigured());
    }

    public function testIsConfiguredIsTrueWhenAllValuesResolve(): void
    {
        putenv('ACLI_DEVICE_CLIENT_ID=env-client');
        putenv('ACLI_OKTA_DOMAIN=env.okta.com');
        putenv('ACLI_OKTA_AUTH_SERVER_ID=ausEnv');

        $this->assertTrue((new OktaConfig())->isConfigured());
    }

    public function testIsConfiguredIsFalseWhenAValueIsSetButEmpty(): void
    {
        putenv('ACLI_DEVICE_CLIENT_ID=env-client');
        putenv('ACLI_OKTA_DOMAIN=env.okta.com');
        putenv('ACLI_OKTA_AUTH_SERVER_ID=');

        $this->assertFalse((new OktaConfig())->isConfigured());
    }
}
