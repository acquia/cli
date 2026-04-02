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
     * Rewrites the API path if it matches any rewrite rule.
     *
     * @param string $path The original API path.
     * @return string The rewritten path, or the original if no rule matches.
     */
    private function rewritePath(string $path): string
    {
        foreach ($this->getPathsToRewrite() as $pattern => $replacement) {
            if (preg_match($pattern, $path) === 1) {
                // Replace the entire path with the replacement if the pattern matches.
                return $replacement;
            }
        }

        // Return the original path if no rewrite rule matches.
        return $path;
    }

    /**
     * Returns an array of regex patterns and their corresponding replacement paths for rewriting API request paths.
     *
     * @return array<string, string> An array of regex patterns and their corresponding replacement paths.
     *   The replacement paths may include the codebase UUID obtained from the AH_CODEBASE_UUID environment variable.
     */
    private function getPathsToRewrite(): array
    {
        $codebaseUuid = $this->getCodeBaseUuid();
        return [
            '#^/applications/[0-9a-f\-]+/environments$#i' => "/translation/codebases/$codebaseUuid/environments",
            '#^/applications/[0-9a-f\-]+/permissions$#i' => "/translation/codebases/$codebaseUuid/permissions",
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
