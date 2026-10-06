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

    /**
     * Skip operations that are explicitly disabled for the CLI channel, or whose
     * audience list is declared but does not include "public".
     * Missing enabled/audience fields default to included.
     */
    protected function shouldSkipOperation(array $schema): bool
    {
        $cli = $schema['x-acquia-exposure']['channels']['cli'] ?? null;
        if ($cli !== null && ($cli['enabled'] ?? true) === false) {
            return true;
        }
        $audience = $schema['x-acquia-exposure']['audience'] ?? null;
        if ($audience === null) {
            return false;
        }
        return !in_array('public', $audience, true);
    }
}
