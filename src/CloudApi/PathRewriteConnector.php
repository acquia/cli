<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use AcquiaCloudApi\Connector\ConnectorInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Decorates a ConnectorInterface to rewrite specific API paths before sending
 * requests. Useful for redirecting legacy or alternative API endpoints to new
 * ones transparently.
 */
final class PathRewriteConnector implements ConnectorInterface
{
    /**
     * The underlying connector to which requests are delegated after path rewriting.
     */
    private ConnectorInterface $inner;

    /**
     * PathRewriteConnector constructor.
     *
     * @param ConnectorInterface $inner The connector to decorate.
     */
    public function __construct(
        ConnectorInterface $inner,
    ) {
        $this->inner = $inner;
    }

    /**
     * Creates a PSR-7 request, rewriting the path if it matches a rewrite rule.
     *
     * @param string $verb HTTP method (e.g., 'GET', 'POST').
     * @param string $path The original API path.
     * @return RequestInterface The PSR-7 request with possibly rewritten path.
     */
    public function createRequest(string $verb, string $path): RequestInterface
    {
        return $this->inner->createRequest($verb, $this->rewritePath($path));
    }

    /**
     * Sends an HTTP request, rewriting the path if it matches a rewrite rule.
     *
     * @param string $verb HTTP method (e.g., 'GET', 'POST').
     * @param string $path The original API path.
     * @param array<string, mixed> $options Additional request options.
     * @return ResponseInterface The HTTP response.
     */
    public function sendRequest(string $verb, string $path, array $options): ResponseInterface
    {
        return $this->inner->sendRequest($verb, $this->rewritePath($path), $options);
    }

    /**
     * Returns the base URI for the API.
     *
     * @return string The base URI.
     */
    public function getBaseUri(): string
    {
        return $this->inner->getBaseUri();
    }

    /**
     * Returns the access token for URL authentication.
     *
     * @return string The access token.
     */
    public function getUrlAccessToken(): string
    {
        return $this->inner->getUrlAccessToken();
    }

    /**
     * Rewrites the API path using preg_replace if it matches any rewrite rule.
     *
     * @param string $path The original API path.
     * @return string The rewritten path, or the original if no rule matches.
     */
    private function rewritePath(string $path): string
    {
        foreach ($this->getPathsToRewrite() as $pattern => $replacement) {
            if (preg_match($pattern, $path) === 1) {
                return (string) preg_replace($pattern, $replacement, $path);
            }
        }

        // Return the original path if no rewrite rule matches.
        return $path;
    }

    /**
     * Returns an array of regex patterns and their corresponding replacement paths for rewriting API request paths.
     *
     * Two rules cover all cases:
     *  - Paths with a trailing segment: /applications/{uuid}/foo/bar → /translation/codebases/{codebaseUuid}/foo/bar
     *  - Bare application UUID paths:   /applications/{uuid}        → /translation/codebases/{codebaseUuid}
     *
     * The first rule uses a capture group ($1) so any trailing path is preserved automatically,
     * avoiding the need to enumerate every possible sub-path.
     *
     * @return array<string, string> Regex pattern => preg_replace replacement string.
     */
    private function getPathsToRewrite(): array
    {
        $codebaseUuid = $this->getCodeBaseUuid();
        return [
            // Matches bare /applications/{uuid} with no trailing segment.
            '#^/applications/[0-9a-f\-]+$#i' => '/translation/codebases/' . $codebaseUuid,
            // Matches /applications/{uuid}/{anything} and preserves the trailing segment via $1.
            '#^/applications/[0-9a-f\-]+/(.+)$#i' => '/translation/codebases/' . $codebaseUuid . '/$1',
        ];
    }

    /**
     * Retrieves the codebase UUID.
     */
    private function getCodeBaseUuid(): string
    {
        $codebaseUuid = getenv('AH_CODEBASE_UUID');
        if (!$codebaseUuid) {
            throw new \RuntimeException('Environment variable AH_CODEBASE_UUID is not set.');
        }
        return $codebaseUuid;
    }
}
