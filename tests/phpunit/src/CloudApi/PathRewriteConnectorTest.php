<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\PathRewriteConnector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PathRewriteConnectorTest extends TestCase
{
    private ConnectorInterface $inner;
    private ConnectorInterface $connector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inner = $this->createMock(ConnectorInterface::class);
        $this->connector = new PathRewriteConnector($this->inner);
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateRequestRewritesMatchingPath(): void
    {
        putenv('AH_CODEBASE_UUID=1234-5678-uuid');
        $expectedPath = '/translation/codebases/1234-5678-uuid/environments';
        $request = $this->createMock(RequestInterface::class);
        $this->inner->expects($this->once())
            ->method('createRequest')
            ->with('GET', $expectedPath)
            ->willReturn($request);
        $result = $this->connector->createRequest('GET', '/applications/abcd-ef01/environments');
        $this->assertSame($request, $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreateRequestDoesNotRewriteUnmatchedPath(): void
    {
        putenv('AH_CODEBASE_UUID=1234-5678-uuid');
        $request = $this->createMock(RequestInterface::class);
        $this->inner->expects($this->once())
            ->method('createRequest')
            ->with('GET', '/other/path')
            ->willReturn($request);
        $result = $this->connector->createRequest('GET', '/other/path');
        $this->assertSame($request, $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendRequestRewritesMatchingPath(): void
    {
        putenv('AH_CODEBASE_UUID=1234-5678-uuid');
        $expectedPath = '/translation/codebases/1234-5678-uuid/permissions';
        $response = $this->createMock(ResponseInterface::class);
        $this->inner->expects($this->once())
            ->method('sendRequest')
            ->with('POST', $expectedPath, ['foo' => 'bar'])
            ->willReturn($response);
        $result = $this->connector->sendRequest('POST', '/applications/abcd-ef01/permissions', ['foo' => 'bar']);
        $this->assertSame($response, $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendRequestDoesNotRewriteUnmatchedPath(): void
    {
        putenv('AH_CODEBASE_UUID=1234-5678-uuid');
        $response = $this->createMock(ResponseInterface::class);
        $this->inner->expects($this->once())
            ->method('sendRequest')
            ->with('GET', '/other/path', [])
            ->willReturn($response);
        $result = $this->connector->sendRequest('GET', '/other/path', []);
        $this->assertSame($response, $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetBaseUriDelegates(): void
    {
        putenv('AH_CODEBASE_UUID=1234-5678-uuid');
        $this->inner->expects($this->once())
            ->method('getBaseUri')
            ->willReturn('https://api.example.com');
        $this->assertSame('https://api.example.com', $this->connector->getBaseUri());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetUrlAccessTokenDelegates(): void
    {
        putenv('AH_CODEBASE_UUID=1234-5678-uuid');
        $this->inner->expects($this->once())
            ->method('getUrlAccessToken')
            ->willReturn('token123');
        $this->assertSame('token123', $this->connector->getUrlAccessToken());
    }

    /**
     * @runInSeparateProcess
     */
    public function testThrowsIfCodebaseUuidNotSet(): void
    {
        putenv('AH_CODEBASE_UUID');
        $connector = new PathRewriteConnector($this->inner);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Environment variable AH_CODEBASE_UUID is not set.');
        // This will trigger getCodeBaseUuid()
        $connector->createRequest('GET', '/applications/abcd-ef01/environments');
    }
}
