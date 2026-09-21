<?php

declare(strict_types=1);

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\DataStore\CloudDataStore;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Token\AccessToken;

class DeviceTokenRefresher
{
    public function __construct(
        private CloudDataStore $datastore,
        private GuzzleClient $httpClient = new GuzzleClient(['timeout' => 15]),
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
        $domain       = getenv('ACLI_OKTA_DOMAIN');
        $authServer   = getenv('ACLI_OKTA_AUTH_SERVER_ID');

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
                ]
            );
            $new = json_decode((string) $response->getBody(), true);
        } catch (ClientException $e) {
            // HTTP 4xx: refresh token is likely expired or revoked.
            $status = $e->getResponse()->getStatusCode();
            if ($status === 400 || $status === 401) {
                error_log('[acli] Device token refresh failed (HTTP ' . $status . '). Run `acli auth:login` to re-authenticate.');
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
}
