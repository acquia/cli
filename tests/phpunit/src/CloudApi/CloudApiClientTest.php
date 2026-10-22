<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\AcsfApi\AcsfClient;
use Acquia\Cli\CloudApi\CloudApiClient;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\ConnectorInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudApiClient::class)]
#[CoversClass(AcsfClient::class)]
class CloudApiClientTest extends TestCase
{
    /**
     * @return array<mixed>
     */
    public static function providerClientClasses(): array
    {
        return [
            [CloudApiClient::class],
            [AcsfClient::class],
        ];
    }

    #[DataProvider('providerClientClasses')]
    public function testJsonResponse(string $clientClass): void
    {
        $client = $clientClass::factory($this->createStub(ConnectorInterface::class));
        $response = new Response(200, [], '{"mail":"user@example.com"}');
        $this->assertEquals('user@example.com', $client->processResponse($response)->mail);
    }

    #[DataProvider('providerClientClasses')]
    public function testHtmlErrorPage(string $clientClass): void
    {
        $client = $clientClass::factory($this->createStub(ConnectorInterface::class));
        $response = new Response(503, ['X-Request-Id' => 'abc123'], '<html><body>Temporarily Unavailable</body></html>');
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('The Acquia API returned HTTP 503 Service Unavailable with a response that is not JSON. The service may be temporarily unavailable; try again later. Request ID: abc123');
        $client->processResponse($response);
    }

    #[DataProvider('providerClientClasses')]
    public function testEmptyBodyWithoutRequestId(string $clientClass): void
    {
        $client = $clientClass::factory($this->createStub(ConnectorInterface::class));
        $response = new Response(200, [], '');
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('The Acquia API returned HTTP 200 OK with a response that is not JSON. The service may be temporarily unavailable; try again later.');
        $client->processResponse($response);
    }
}
