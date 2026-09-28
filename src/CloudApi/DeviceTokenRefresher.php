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
    /**
     * Refresh this long before expiry, so in-flight requests don't 401.
     */
    private const PRE_EXPIRY_WINDOW_SECONDS = 60;

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
     * Returns null when no token is stored, the refresh token is missing, or the
     * refresh request fails — callers should surface a re-login prompt.
     */
    public function getValidAccessToken(): ?string
    {
        $stored = $this->datastore->get('device_token');
        if (!$stored || empty($stored['access_token'])) {
            return null;
        }

        if ($this->hasLifeLeft($stored)) {
            return $stored['access_token'];
        }

        // Presenting a rotated refresh token invalidates the whole session, so only
        // one invocation may refresh a given token.
        $lock = $this->acquireLock();
        try {
            return $this->refresh();
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * Performs the refresh, assuming the caller holds the lock.
     */
    private function refresh(): ?string
    {
        $stored = $this->readTokenFromDisk();
        if (!$stored || empty($stored['access_token'])) {
            return null;
        }
        if ($this->hasLifeLeft($stored)) {
            return $stored['access_token'];
        }

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
                $this->logger->warning('Device token refresh failed: the refresh token is expired or revoked. Run `acli auth:login` to re-authenticate.');
                return null;
            }
            if ($status === 400 || $status === 401) {
                $this->logger->warning('Device token refresh failed (HTTP {status}). Run `acli auth:login` to re-authenticate.', ['status' => $status]);
            }
            return null;
        } catch (GuzzleException) {
            return null;
        }

        if (empty($new['access_token'])) {
            return null;
        }

        $this->persistToken([
            'access_token'  => $new['access_token'],
            'client_id'     => $clientId,
            'expiry'        => isset($new['expires_in']) ? time() + (int) $new['expires_in'] : 0,
            'refresh_token' => $new['refresh_token'] ?? $refreshToken,
        ]);

        return $new['access_token'];
    }

    /**
     * @param array<string, mixed> $token
     */
    private function hasLifeLeft(array $token): bool
    {
        $accessToken = new AccessToken([
            'access_token' => $token['access_token'],
            'expires'      => $token['expiry'] ?? 0,
        ]);

        return ($accessToken->getExpires() - time()) > self::PRE_EXPIRY_WINDOW_SECONDS;
    }

    /**
     * Reads `device_token` from disk, bypassing the in-memory datastore.
     *
     * @return array<string, mixed>|null
     */
    private function readTokenFromDisk(): ?array
    {
        $contents = @file_get_contents($this->datastore->filepath);
        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded) || !isset($decoded['device_token']) || !is_array($decoded['device_token'])) {
            return null;
        }

        return $decoded['device_token'];
    }

    /**
     * Writes `device_token` without disturbing anything else in the file.
     *
     * @param array<string, mixed> $token
     */
    private function persistToken(array $token): void
    {
        $path = $this->datastore->filepath;
        $contents = @file_get_contents($path);
        $data = $contents === false ? null : json_decode($contents, true);

        if (!is_array($data)) {
            $this->datastore->set('device_token', $token);
            return;
        }

        $data['device_token'] = $token;
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        chmod($path, 0600);
    }

    /**
     * Takes an exclusive lock on a sidecar file beside the datastore.
     *
     * @return resource|null
     */
    private function acquireLock()
    {
        $handle = @fopen($this->datastore->filepath . '.lock', 'c');
        if ($handle === false) {
            $this->logger->warning('Could not open the device token lock file; refreshing without serialisation.');
            return null;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            $this->logger->warning('Could not lock the device token lock file; refreshing without serialisation.');
            return null;
        }

        return $handle;
    }
}
