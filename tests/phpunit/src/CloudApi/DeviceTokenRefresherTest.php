<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\DeviceTokenRefresher;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class DeviceTokenRefresherTest extends TestBase
{
    /** @var array<string, string|false> */
    private array $savedEnvVars = [];

    private ObjectProphecy $datastoreProphecy;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ACLI_OKTA_DOMAIN', 'ACLI_OKTA_AUTH_SERVER_ID'] as $var) {
            $this->savedEnvVars[$var] = getenv($var);
            putenv($var);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->savedEnvVars as $var => $value) {
            putenv($value === false ? $var : $var . '=' . $value);
        }
    }

    private function createRefresher(array $responses = []): DeviceTokenRefresher
    {
        $mock = new MockHandler($responses);
        $client = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        $datastoreProphecy = $this->prophet->prophesize(CloudDataStore::class);
        $this->datastoreProphecy = $datastoreProphecy;

        return new DeviceTokenRefresher($datastoreProphecy->reveal(), $client);
    }

    public function testReturnsNullWhenNoTokenStored(): void
    {
        $refresher = $this->createRefresher();
        $this->datastoreProphecy->get('device_token')->willReturn(null);

        $this->assertNull($refresher->getValidAccessToken());
    }

    public function testReturnsNullWhenStoredTokenHasNoAccessToken(): void
    {
        $refresher = $this->createRefresher();
        $this->datastoreProphecy->get('device_token')->willReturn(['access_token' => '']);

        $this->assertNull($refresher->getValidAccessToken());
    }

    public function testReturnsStoredTokenWhenNotExpired(): void
    {
        $refresher = $this->createRefresher();
        $this->datastoreProphecy->get('device_token')->willReturn([
            'access_token' => 'valid-token',
            'expiry' => time() + 300,
        ]);

        $this->assertEquals('valid-token', $refresher->getValidAccessToken());
    }

    public function testReturnsNullWhenExpiredAndRefreshTokenMissing(): void
    {
        putenv('ACLI_OKTA_DOMAIN=example.okta.com');
        putenv('ACLI_OKTA_AUTH_SERVER_ID=ausTest');
        $refresher = $this->createRefresher();
        $this->datastoreProphecy->get('device_token')->willReturn([
            'access_token' => 'expired-token',
            'client_id' => 'client-123',
            'expiry' => time() - 300,
        ]);

        $this->assertNull($refresher->getValidAccessToken());
    }

    public function testReturnsNullWhenExpiredAndEnvVarsMissing(): void
    {
        // ACLI_OKTA_DOMAIN / ACLI_OKTA_AUTH_SERVER_ID are unset per setUp().
        $refresher = $this->createRefresher();
        $this->datastoreProphecy->get('device_token')->willReturn([
            'access_token' => 'expired-token',
            'client_id' => 'client-123',
            'expiry' => time() - 300,
            'refresh_token' => 'refresh-123',
        ]);

        $this->assertNull($refresher->getValidAccessToken());
    }

    public function testRefreshesExpiredTokenSuccessfully(): void
    {
        putenv('ACLI_OKTA_DOMAIN=example.okta.com');
        putenv('ACLI_OKTA_AUTH_SERVER_ID=ausTest');
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
                'refresh_token' => 'new-refresh-token',
            ])),
        ]);
        $this->datastoreProphecy->get('device_token')->willReturn([
            'access_token' => 'expired-token',
            'client_id' => 'client-123',
            'expiry' => time() - 300,
            'refresh_token' => 'old-refresh-token',
        ]);
        $this->datastoreProphecy->set('device_token', Argument::that(
            fn (array $token): bool => $token['access_token'] === 'new-access-token'
                && $token['client_id'] === 'client-123'
                && $token['refresh_token'] === 'new-refresh-token'
                && is_int($token['expiry'])
        ))->shouldBeCalled();

        $this->assertEquals('new-access-token', $refresher->getValidAccessToken());
    }

    public function testKeepsOldRefreshTokenWhenResponseOmitsNewOne(): void
    {
        putenv('ACLI_OKTA_DOMAIN=example.okta.com');
        putenv('ACLI_OKTA_AUTH_SERVER_ID=ausTest');
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ])),
        ]);
        $this->datastoreProphecy->get('device_token')->willReturn([
            'access_token' => 'expired-token',
            'client_id' => 'client-123',
            'expiry' => time() - 300,
            'refresh_token' => 'old-refresh-token',
        ]);
        $this->datastoreProphecy->set('device_token', Argument::that(
            fn (array $token): bool => $token['access_token'] === 'new-access-token'
                && $token['client_id'] === 'client-123'
                && $token['refresh_token'] === 'old-refresh-token'
                && is_int($token['expiry'])
        ))->shouldBeCalled();

        $this->assertEquals('new-access-token', $refresher->getValidAccessToken());
    }

    public function testReturnsNullWhenRefreshResponseHasNoAccessToken(): void
    {
        putenv('ACLI_OKTA_DOMAIN=example.okta.com');
        putenv('ACLI_OKTA_AUTH_SERVER_ID=ausTest');
        $refresher = $this->createRefresher([
            new Response(200, [], json_encode(['error' => 'invalid_grant'])),
        ]);
        $this->datastoreProphecy->get('device_token')->willReturn([
            'access_token' => 'expired-token',
            'client_id' => 'client-123',
            'expiry' => time() - 300,
            'refresh_token' => 'old-refresh-token',
        ]);

        $this->assertNull($refresher->getValidAccessToken());
    }

    public function testReturnsNullOnRefreshRequestFailure(): void
    {
        putenv('ACLI_OKTA_DOMAIN=example.okta.com');
        putenv('ACLI_OKTA_AUTH_SERVER_ID=ausTest');
        $refresher = $this->createRefresher([
            new Response(400, [], json_encode(['error' => 'invalid_grant'])),
        ]);
        $this->datastoreProphecy->get('device_token')->willReturn([
            'access_token' => 'expired-token',
            'client_id' => 'client-123',
            'expiry' => time() - 300,
            'refresh_token' => 'old-refresh-token',
        ]);

        $this->assertNull($refresher->getValidAccessToken());
    }
}
