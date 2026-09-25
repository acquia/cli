<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

/**
 * Okta device code configuration, shared by login and token refresh.
 */
class OktaConfig
{
    public function authServerId(): string
    {
        return getenv('ACLI_OKTA_AUTH_SERVER_ID') ?: '';
    }

    public function clientId(): string
    {
        return getenv('ACLI_DEVICE_CLIENT_ID') ?: '';
    }

    public function domain(): string
    {
        return getenv('ACLI_OKTA_DOMAIN') ?: '';
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== ''
            && $this->domain() !== ''
            && $this->authServerId() !== '';
    }
}
