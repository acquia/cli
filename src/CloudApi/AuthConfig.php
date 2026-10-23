<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

/**
 * Authorization-server configuration for the device code flow ,
 * shared by login and token refresh.
 */
class AuthConfig
{
    public function authServerId(): string
    {
        return getenv('ACLI_AUTH_SERVER_ID') ?: '';
    }

    public function clientId(): string
    {
        return getenv('ACLI_DEVICE_CLIENT_ID') ?: '';
    }

    public function domain(): string
    {
        return getenv('ACLI_AUTH_DOMAIN') ?: '';
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== ''
            && $this->domain() !== ''
            && $this->authServerId() !== '';
    }

    /**
     * The organization scope for a federated-auth sign-in, or '' when there is none.
     *
     * Federated-auth customers belong to a domain-locked organization, and the
     * Cloud API resolves it from an `organization:<uuid>` scope. On the key and
     * secret path the SDK's `Connector` reads `AH_ORGANIZATION_UUID` and asks
     * for that scope on every `client_credentials` call; device code has to ask
     * for it when the device authorization is issued, because that is where the
     * scope is bound — the poll and every later refresh inherit it.
     *
     * Takes the value rather than reading the environment so the parsing can be
     * tested outside the serial group.
     */
    public static function organizationScope(string|false $orgUuid): string
    {
        $orgUuid = trim((string) $orgUuid);

        // The value lands in a space-delimited scope string, so anything that is
        // not a bare UUID could smuggle in scopes of its own. A malformed value
        // is ignored rather than sent, so a stray export cannot break sign-in
        // for everyone else.
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $orgUuid) !== 1) {
            return '';
        }

        return 'organization:' . $orgUuid;
    }
}
