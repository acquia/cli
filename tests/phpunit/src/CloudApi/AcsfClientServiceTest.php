<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfConnectorFactory;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use GuzzleHttp\Psr7\Response;

class AcsfClientServiceTest extends TestBase
{
    protected static string $apiSpecFixtureFilePath = __DIR__ . '/../../../../assets/acsf-spec.json';

    protected string $apiCommandPrefix = 'acsf';

    /**
     * @return array<mixed>
     */
    public static function providerTestIsMachineAuthenticated(): array
    {
        return [
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

    /**
     * @dataProvider providerTestIsMachineAuthenticated
     */
    public function testIsMachineAuthenticated(array $envVars, bool $isAuthenticated): void
    {
        self::setEnvVars($envVars);
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $clientService = new AcsfClientService(new AcsfConnectorFactory([
            'accessToken' => null,
            'key' => null,
            'secret' => null,
        ]), $this->application, new AcsfCredentials($cloudDatastore->reveal()));
        $this->assertEquals($isAuthenticated, $clientService->isMachineAuthenticated());
        $clientService->getClient();
        self::unsetEnvVars($envVars);
    }

    public function testEmbeddedItems(): void
    {
        putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $clientService = new AcsfClientService(new AcsfConnectorFactory([
            'accessToken' => null,
            'key' => null,
            'secret' => null,
        ]), $this->application, new AcsfCredentials($cloudDatastore->reveal()));
        $client = $clientService->getClient();
        $mockBody = ['_embedded' => ['items' => 'foo']];
        $response = new Response(200, [], json_encode($mockBody));
        $body = $client->processResponse($response);
        $this->assertEquals('foo', $body);
    }

    public function testErrorMessage(): void
    {
        putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $clientService = new AcsfClientService(new AcsfConnectorFactory([
            'accessToken' => null,
            'key' => null,
            'secret' => null,
        ]), $this->application, new AcsfCredentials($cloudDatastore->reveal()));
        $client = $clientService->getClient();
        $mockBody = ['error' => 'foo', 'message' => 'bar'];
        $response = new Response(200, [], json_encode($mockBody));
        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('bar');
        $client->processResponse($response);
    }

    public function testErrorCode(): void
    {
        putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');
        $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
        $clientService = new AcsfClientService(new AcsfConnectorFactory([
            'accessToken' => null,
            'key' => null,
            'secret' => null,
        ]), $this->application, new AcsfCredentials($cloudDatastore->reveal()));
        $client = $clientService->getClient();
        $mockBody = ['message' => 'bar'];
        $response = new Response(400, [], json_encode($mockBody));
        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('bar');
        $client->processResponse($response);
    }
}
