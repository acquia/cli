<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\PathRewriteConnector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \Acquia\Cli\CloudApi\PathRewriteConnector
 *
 * Unit tests for the PathRewriteConnector decorator. Ensures all path rewriting logic,
 * delegation, and error handling are correct.
 */
class PathRewriteConnectorTest extends TestCase
{
    /**
     * Mocked inner connector to verify delegation and path rewriting.
     */
    private ConnectorInterface $inner;

    /**
     * The PathRewriteConnector under test.
     */
    private ConnectorInterface $connector;

    /**
     * Stores the original value of AH_CODEBASE_UUID to restore after each test.
     */
    private string|bool $originalEnv;

    /**
     * Sets up a fresh PathRewriteConnector and mocks before each test.
     * Ensures AH_CODEBASE_UUID is set for tests that require it.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = getenv('AH_CODEBASE_UUID');
        putenv('AH_CODEBASE_UUID=1234-5678-uuid');
        $this->inner = $this->createMock(ConnectorInterface::class);
        $this->connector = new PathRewriteConnector($this->inner);
    }

    /**
     * @dataProvider createRequestProvider
     * @param string $verb The HTTP verb to test.
     * @param string $inputPath The input path to test.
     * @param string $expectedPath The expected path after rewriting.
     */
    public function testCreateRequestPathRewriting(string $verb, string $inputPath, string $expectedPath): void
    {
        $mock = $this->createMock(RequestInterface::class);
        $this->inner->expects($this->once())
            ->method('createRequest')
            ->with($verb, $expectedPath)
            ->willReturn($mock);
        $result = $this->connector->createRequest($verb, $inputPath);
        $this->assertSame($mock, $result);
    }



    /**
     * @dataProvider sendRequestProvider
     * @param string $verb The HTTP verb to test.
     * @param string $inputPath The input path to test.
     * @param string $expectedPath The expected path after rewriting.
     * @param array $options The options to pass to sendRequest.
     */
    public function testSendRequestPathRewriting(string $verb, string $inputPath, string $expectedPath, array $options): void
    {
        $mock = $this->createMock(ResponseInterface::class);
        $this->inner->expects($this->once())
            ->method('sendRequest')
            ->with($verb, $expectedPath, $options)
            ->willReturn($mock);
        $result = $this->connector->sendRequest($verb, $inputPath, $options);
        $this->assertSame($mock, $result);
    }

    /**
     * @dataProvider delegationProvider
     * @param string $method The method to test delegation for.
     * @param mixed $expected The expected return value from the inner connector.
     */
    public function testDelegation(string $method, string $expected): void
    {
        $this->inner->expects($this->once())
            ->method($method)
            ->willReturn($expected);
        $this->assertTrue(method_exists($this->connector, $method));
        $this->assertSame($expected, $this->connector->{$method}());
    }

    /**
     * Ensures an exception is thrown if AH_CODEBASE_UUID is not set when required.
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

    /**
     * Data provider for createRequest tests. Ensures that paths are rewritten
     * correctly based on the presence of the code base environment variable.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function createRequestProvider(): array
    {
        return [
            'account path is not rewritten' => [
                'GET',
                '/account',
                '/account',
            ],
            // Bare UUID (no trailing segment) is also rewritten.
            'bare application UUID is rewritten' => [
                'GET',
                '/applications/abcd-ef01',
                '/translation/codebases/1234-5678-uuid',
            ],
            // Deep sub-path: entire trailing part is preserved by $1.
            'deep sub-path is rewritten' => ['GET',
                '/applications/abcd-ef01/environments/env-1/tags',
                '/translation/codebases/1234-5678-uuid/environments/env-1/tags',
            ],
            // Single trailing segment is rewritten via capture group ($1).
            'environments path is rewritten' => [
                'GET',
                '/applications/abcd-ef01/environments',
                '/translation/codebases/1234-5678-uuid/environments',
            ],
            'permissions path is rewritten' => [
                'GET',
                '/applications/abcd-ef01/permissions',
                '/translation/codebases/1234-5678-uuid/permissions',
            ],
            // Paths that do not start with /applications/{uuid} are left unchanged.
            'unrelated path is not rewritten' => [
                'GET',
                '/other/path',
                '/other/path',
            ],
        ];
    }

    /**
     * Data provider for sendRequest tests. Ensures that both path rewriting
     * and options are handled correctly.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: array<string, mixed>}>
     */
    public static function sendRequestProvider(): array
    {
        return [
            // Bare UUID (no trailing segment) is also rewritten.
            'bare application UUID is rewritten' => [
                'GET',
                '/applications/abcd-ef01',
                '/translation/codebases/1234-5678-uuid',
                [],
            ],
            // Deep sub-path: entire trailing part is preserved by $1.
            'deep sub-path is rewritten' => [
                'POST',
                '/applications/abcd-ef01/environments/env-1/tags',
                '/translation/codebases/1234-5678-uuid/environments/env-1/tags',
                [],
            ],
            // Single trailing segment is rewritten via capture group ($1).
            'permissions path is rewritten' => [
                'POST',
                '/applications/abcd-ef01/permissions',
                '/translation/codebases/1234-5678-uuid/permissions',
                ['foo' => 'bar'],
            ],
            // Paths that do not start with /applications/{uuid} are left unchanged.
            'unrelated path is not rewritten' => [
                'GET',
                '/other/path',
                '/other/path',
                [],
            ],
        ];
    }

    /**
     * Data provider for delegation tests. Ensures that methods not related to
     * path rewriting are properly delegated to the inner connector.
     *
     * @return array<int, array{0: string, 1: mixed}>
     */
    public static function delegationProvider(): array
    {
        return [
            ['getBaseUri', 'https://api.example.com'],
            ['getUrlAccessToken', 'token123'],
        ];
    }

    /**
     * Restores the original AH_CODEBASE_UUID environment variable after each test.
     */
    protected function tearDown(): void
    {
        if ($this->originalEnv === false) {
            putenv('AH_CODEBASE_UUID');
        } else {
            putenv('AH_CODEBASE_UUID=' . $this->originalEnv);
        }
        parent::tearDown();
    }
}
