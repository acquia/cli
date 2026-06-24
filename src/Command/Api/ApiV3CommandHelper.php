<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Api;

/**
 * Command helper for Cloud API v3 (OpenAPI 3.1+) specs. Overrides
 * extension-field lookups that diverge from the legacy v2 convention.
 */
class ApiV3CommandHelper extends ApiCommandHelper
{
    /**
     * v3 specs declare CLI command names under
     * `x-acquia-exposure.channels.cli.command`. v3 reads ONLY that key;
     * legacy `x-cli-name` is v2's concern and handled by ApiCommandHelper.
     */
    protected function getCliCommandName(array $schema): ?string
    {
        return $schema['x-acquia-exposure']['channels']['cli']['command'] ?? null;
    }

    protected function getSchemaStability(array $schema): ?string
    {
        return $schema['x-acquia-exposure']['stability'] ?? null;
    }
}
