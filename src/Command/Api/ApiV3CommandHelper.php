<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Api;

/**
 * Command helper for Cloud API v3 (OpenAPI 3.1+) specs sourced from
 * acquia/api-specs. Overrides extension-field lookups and gateway-prefix
 * handling that diverge from the legacy v2 convention.
 */
class ApiV3CommandHelper extends ApiCommandHelper
{
    /**
     * Path prefixes whose services sit behind the `/v3/` gateway route.
     * Site-service paths (`/sites/...`) are deliberately absent — they live
     * at `/api/sites/...` on the live gateway, not `/api/v3/sites/...`.
     */
    private const V3_PATH_PREFIXES = [
        '/environments/',
        '/site-instances/',
        '/deployments/',
    ];

    /**
     * Per ARB-550, v3 specs declare CLI command names under
     * `x-acquia-exposure.channels.cli.command`. v3 reads ONLY that key;
     * legacy `x-cli-name` is v2's concern and handled by ApiCommandHelper.
     * If the upstream spec still ships `x-cli-name`, the composer bundling
     * step rewrites it to `x-acquia-exposure` so this method receives the
     * ARB-compliant shape.
     */
    protected function getCliCommandName(array $schema): ?string
    {
        return $schema['x-acquia-exposure']['channels']['cli']['command'] ?? null;
    }

    /**
     * Prepends `/v3` to paths whose service is routed through the v3 gateway
     * prefix on the live Cloud Platform API. The upstream spec declares logical
     * paths without the version prefix (per ARB-437, the prefix is a
     * gateway-level concern); ACLI talks to the public gateway directly, so
     * the prefix must be supplied here.
     */
    protected function normalizePath(string $path): string
    {
        foreach (self::V3_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return '/v3' . $path;
            }
        }
        return $path;
    }
}
