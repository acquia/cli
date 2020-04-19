<?php

namespace Acquia\Ads;

use Acquia\Ads\DataStore\DataStoreInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class CloudApiClient
{

    /**
     * CloudApiClient constructor.
     *
     * @param \Acquia\Ads\DataStore\DataStoreInterface $datastore
     */
    public function __construct(DataStoreInterface $datastore)
    {
        // See https://docs.acquia.com/acquia-cloud/develop/api/auth/
        // for how to generate a client ID and Secret.
        $cloud_api_conf = $datastore->get('cloud_api.conf');
        // @todo If this is empty, prompt to authenticate.
        $this->provider = new GenericProvider([
          'clientId'                => $cloud_api_conf['key'],
          'clientSecret'            => $cloud_api_conf['secret'],
          'urlAuthorize'            => '',
          'urlAccessToken'          => 'https://accounts.acquia.com/api/auth/oauth/token',
          'urlResourceOwnerDetails' => '',
        ]);
    }

    /**
     * @param $api_url
     * @param $path
     * @param $query
     * @param $method
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request($api_url, $path, $query, $method): ?ResponseInterface
    {
        try {
            // Try to get an access token using the client credentials grant.
            // @todo See if we already have a valid access token.
            $accessToken = $this->provider->getAccessToken('client_credentials');

            // Generate a request object using the access token.
            $request = $this->provider->getAuthenticatedRequest(
                $method,
                $api_url . '/' . ltrim($path, '/'),
                $accessToken
            );

            $options = [
              'query' => $query,
            ];

            // Send the request.
            $client = new Client();
            return $client->send($request, $options);
        } catch (IdentityProviderException $e) {
            // Failed to get the access token.
            exit($e->getMessage());
        }
    }
}
