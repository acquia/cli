<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

/**
 * The outcome of a device token refresh.
 *
 * A bare null can't distinguish "your session is over, sign in again" from
 * "the authorization server was unreachable, try again" — and telling a
 * developer to re-authenticate because their wifi dropped costs them a browser
 * round trip for nothing. The two outcomes are separated here so the caller can
 * say which happened.
 */
final class DeviceTokenRefreshResult
{
    private function __construct(
        public readonly ?string $accessToken,
        public readonly bool $serverUnreachable,
    ) {
    }

    public static function token(string $accessToken): self
    {
        return new self($accessToken, false);
    }

    /**
     * The session is over: `invalid_grant`, no refresh token, or no auth config.
     *
     * Nothing short of signing in again will fix it.
     */
    public static function sessionEnded(): self
    {
        return new self(null, false);
    }

    /**
     * A transport failure, 5xx, or 429. The stored token is still good.
     */
    public static function serverUnreachable(): self
    {
        return new self(null, true);
    }
}
