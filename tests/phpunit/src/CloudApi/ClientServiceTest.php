<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudApiClient;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\CloudApi\V3ClientService;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Tests\TestBase;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

class ClientServiceTest extends TestBase
{
    /**
     * @return array<string, array{bool|null, bool}>
     */
    public static function providerTelemetryIdInUserAgent(): array
    {
        return [
            'never asked' => [null, false],
            'opted in' => [true, true],
            'opted out' => [false, false],
        ];
    }

    /**
     * The telemetry ID identifies the machine, so it is sent only on consent.
     *
     * Its slot stays even when empty, so a reader splitting on commas never
     * mistakes the provider for the ID. The provider identifies no one and is
     * always sent.
     */
    #[DataProvider('providerTelemetryIdInUserAgent')]
    #[Group('serial')]
    public function testUserAgentCarriesTelemetryIdOnlyWithConsent(?bool $preference, bool $expectId): void
    {
        putenv('LANDO=1');
        try {
            if ($preference === null) {
                $this->datastoreCloud->remove(DataStoreContract::SEND_TELEMETRY);
            } else {
                $this->datastoreCloud->set(DataStoreContract::SEND_TELEMETRY, $preference);
            }

            $this->assertSame(
                $expectId
                    // Mirrors the client's fallback: a machine whose ID is
                    // rejected sends the literal 'null', not an empty slot.
                    ? sprintf('Acquia CLI (UNKNOWN, %s, lando)', TelemetryHelper::getMachineId() ?: 'null')
                    : 'Acquia CLI (UNKNOWN, null, lando)',
                $this->userAgentClientService()->getClient()->getOptions()['headers']['User-Agent'][0],
            );
        } finally {
            putenv('LANDO');
        }
    }

    /**
     * ACLI_NO_TELEMETRY turns telemetry off without touching the stored answer,
     * so it has to suppress the ID even when that answer is yes.
     */
    #[Group('serial')]
    public function testUserAgentOmitsTelemetryIdWhenSuppressedByEnvVar(): void
    {
        putenv('LANDO=1');
        putenv('ACLI_NO_TELEMETRY=true');
        try {
            $this->datastoreCloud->set(DataStoreContract::SEND_TELEMETRY, true);

            $this->assertSame(
                'Acquia CLI (UNKNOWN, null, lando)',
                $this->userAgentClientService()->getClient()->getOptions()['headers']['User-Agent'][0],
            );
        } finally {
            putenv('LANDO');
            putenv('ACLI_NO_TELEMETRY');
        }
    }

    /**
     * Asserting the header is constructible, rather than comparing it to a
     * string built from the same source, is what catches an unsanitised
     * machine ID: Guzzle rejects a value containing a control character.
     */
    #[Group('serial')]
    public function testUserAgentIsAlwaysAValidHeaderValue(): void
    {
        putenv('LANDO=1');
        try {
            $this->datastoreCloud->set(DataStoreContract::SEND_TELEMETRY, true);
            $userAgent = $this->userAgentClientService()->getClient()->getOptions()['headers']['User-Agent'][0];

            $request = new Request('GET', 'https://cloud.acquia.com/api/account', ['User-Agent' => $userAgent]);
            $this->assertSame($userAgent, $request->getHeaderLine('User-Agent'));
        } finally {
            putenv('LANDO');
        }
    }

    private function userAgentClientService(): ClientService
    {
        return new ClientService(
            new ConnectorFactory(['accessToken' => null, 'key' => null, 'secret' => null]),
            $this->application,
            $this->cloudCredentials,
            $this->datastoreCloud,
        );
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestIsMachineAuthenticated(): array
    {
        return [
            [
                [
                    'ACLI_ACCESS_TOKEN' => 'token',
                    'ACLI_KEY' => 'key',
                    'ACLI_SECRET' => 'secret',
                ],
                true,
            ],
            [
                [
                    'ACLI_ACCESS_TOKEN' => null,
                    'ACLI_KEY' => 'key',
                    'ACLI_SECRET' => 'secret',
                ],
                true,
            ],
            [
                [
                    'ACLI_ACCESS_TOKEN' => null,
                    'ACLI_KEY' => null,
                    'ACLI_SECRET' => null,
                ],
                false,
            ],
            [
                [
                    'ACLI_ACCESS_TOKEN' => null,
                    'ACLI_KEY' => 'key',
                    'ACLI_SECRET' => null,
                ],
                false,
            ],
        ];
    }

    #[DataProvider('providerTestIsMachineAuthenticated')]
    public function testIsMachineAuthenticated(array $envVars, bool $isAuthenticated): void
    {
        self::setEnvVars($envVars);
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $clientService = new ClientService(new ConnectorFactory([
            'accessToken' => null,
            'key' => null,
            'secret' => null,
        ]), $this->application, new CloudCredentials($cloudDatastore->reveal()));
        $this->assertEquals($isAuthenticated, $clientService->isMachineAuthenticated());
        self::unsetEnvVars($envVars);
    }

    public function testASubclassOverridesTheAuthenticationCheck(): void
    {
        $envVars = [
            'ACLI_ACCESS_TOKEN' => null,
            'ACLI_KEY' => 'key',
            'ACLI_SECRET' => 'secret',
        ];
        self::setEnvVars($envVars);
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $clientService = new TestOverridingClientService(new ConnectorFactory([
            'accessToken' => null,
            'key' => null,
            'secret' => null,
        ]), $this->application, new CloudCredentials($cloudDatastore->reveal()));

        $this->assertTrue($clientService->isMachineAuthenticated());
        $this->assertTrue($clientService->overrideWasCalled);
        self::unsetEnvVars($envVars);
    }

    public function testV3ClientServiceConstructs(): void
    {
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $credentials = new CloudCredentials($cloudDatastore->reveal());
        $connectorFactory = new ConnectorFactory([
            'accessToken' => null,
            'key' => null,
            'secret' => null,
        ]);
        $v3Service = new V3ClientService($connectorFactory, $this->application, $credentials);
        $this->assertFalse($v3Service->isMachineAuthenticated());
        $this->assertInstanceOf(CloudApiClient::class, $v3Service->getClient());
    }
}
