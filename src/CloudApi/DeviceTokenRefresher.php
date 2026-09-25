<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\DataStore\CloudDataStore;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

class DeviceTokenRefresher
{
    private const REQUEST_TIMEOUT_SECONDS = 15;

    public function __construct(
        private CloudDataStore $datastore,
        private LoggerInterface $logger,
        private GuzzleClient $httpClient = new GuzzleClient(),
        private OktaConfig $oktaConfig = new OktaConfig(),
    ) {
    }

    /**
     * Returns a valid device access token, refreshing silently if expired.
     *
     * Returns null when no token is stored, the refresh token is missing, or
     * the refresh request fails — callers should fall back to other auth methods.
     */
    public function getValidAccessToken(): ?string
    {
        $stored = $this->datastore->get('device_token');
        if (!$stored || empty($stored['access_token'])) {
            return null;
        }

        $token = new AccessToken([
            'access_token' => $stored['access_token'],
            'expires'      => $stored['expiry'] ?? 0,
        ]);

        // Refresh 60 s before actual expiry so requests in-flight don't hit a 401.
        if (($token->getExpires() - time()) > 60) {
            return $stored['access_token'];
        }

        // Access token expired — attempt a silent refresh.
        $refreshToken = $stored['refresh_token'] ?? null;
        $clientId     = $stored['client_id'] ?? null;
        $domain       = $this->oktaConfig->domain();
        $authServer   = $this->oktaConfig->authServerId();

        if (!$refreshToken || !$clientId || !$domain || !$authServer) {
            return null;
        }

        try {
            $response = $this->httpClient->post(
                sprintf('https://%s/oauth2/%s/v1/token', $domain, $authServer),
                [
                    'form_params' => [
                        'client_id'     => $clientId,
                        'grant_type'    => 'refresh_token',
                        'refresh_token' => $refreshToken,
                    ],
                    'timeout'     => self::REQUEST_TIMEOUT_SECONDS,
                ]
            );
            $new = json_decode((string) $response->getBody(), true);
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            $body = json_decode((string) $e->getResponse()->getBody(), true);
            $error = is_array($body) ? ($body['error'] ?? '') : '';

            if ($error === 'invalid_grant') {
                if ($winnersToken = $this->tokenWrittenByAnotherProcess($stored)) {
                    return $winnersToken;
                }
                $this->logger->warning('Device token refresh failed: the refresh token is expired or revoked. Run `acli auth:login` to re-authenticate.');
                return null;
            }

            if ($status === 400 || $status === 401) {
                $this->logger->warning('Device token refresh failed (HTTP {status}). Run `acli auth:login` to re-authenticate.', ['status' => $status]);
            }
            return null;
        } catch (GuzzleException) {
            // Network / transport error — fail silently; caller falls back.
            return null;
        }

        if (empty($new['access_token'])) {
            return null;
        }

        $this->datastore->set('device_token', [
            'access_token'  => $new['access_token'],
            'client_id'     => $clientId,
            'expiry'        => isset($new['expires_in']) ? time() + (int) $new['expires_in'] : 0,
            'refresh_token' => $new['refresh_token'] ?? $refreshToken,
        ]);

        return $new['access_token'];
    }

    /**
     * Returns a usable access token another process wrote while we were refreshing.
     *
     * Null means nothing changed, so the refresh token really is expired or revoked.
     *
     * @param array<string, mixed> $stored the token this call started with
     */
    private function tokenWrittenByAnotherProcess(array $stored): ?string
    {
        $current = $this->datastore->get('device_token');
        if (!is_array($current) || empty($current['access_token'])) {
            return null;
        }
        if ($current['access_token'] === ($stored['access_token'] ?? null)) {
            return null;
        }
        if ((($current['expiry'] ?? 0) - time()) <= 60) {
            return null;
        }

        return $current['access_token'];
    }
}
