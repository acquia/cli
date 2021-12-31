<?php

namespace Acquia\Cli\CloudApi;

use Acquia\Cli\Application;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use Webmozart\KeyValueStore\JsonFileStore;
use Webmozart\KeyValueStore\Util\Serializer;

/**
 * Factory producing Acquia Cloud Api clients.
 *
 * This class is only necessary as a testing shim, so that we can prophesize
 * client queries. Consumers could otherwise just call
 * Client::factory($connector) directly.
 *
 * @package Acquia\Cli\Helpers
 */
class ClientService {

  /**
   * @var ConnectorInterface
   */
  private $connector;

  /**
   * @var \Acquia\Cli\CloudApi\ConnectorFactory
   */
  private $connectorFactory;

  /**
   * @var Application
   */
  private Application $application;

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  private JsonFileStore $datastoreCloud;

  /**
   * @var \Acquia\Cli\CloudApi\CloudCredentials
   */
  private CloudCredentials $cloudCredentials;

  /**
   * @param \Acquia\Cli\CloudApi\ConnectorFactory $connector_factory
   * @param \Acquia\Cli\Application $application
   * @param \Webmozart\KeyValueStore\JsonFileStore $datastore_cloud
   * @param \Acquia\Cli\CloudApi\CloudCredentials $cloud_credentials
   */
  public function __construct(
    ConnectorFactory $connector_factory,
    Application $application,
    JsonFileStore $datastore_cloud,
    CloudCredentials $cloud_credentials
  ) {
    $this->connectorFactory = $connector_factory;
    $this->setConnector($connector_factory->createConnector());
    $this->setApplication($application);
    $this->datastoreCloud = $datastore_cloud;
    $this->cloudCredentials = $cloud_credentials;
  }

  /**
   * @param \AcquiaCloudApi\Connector\ConnectorInterface $connector
   */
  public function setConnector(ConnectorInterface $connector): void {
    $this->connector = $connector;
  }

  /**
   * @param \Acquia\Cli\Application $application
   */
  public function setApplication(Application $application): void {
    $this->application = $application;
  }

  /**
   * @return \AcquiaCloudApi\Connector\Client
   */
  public function getClient(): Client {
    $client = Client::factory($this->connector);
    $this->configureClient($client);

    return $client;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $client
   */
  protected function configureClient(Client $client): void {
    $user_agent = sprintf("acli/%s", $this->application->getVersion());
    $client->addOption('headers', [
      'User-Agent' => [$user_agent],
    ]);
  }

  /**
   * @param string $api_key
   * @param string $api_secret
   * @param string $base_uri
   */
  protected function reAuthenticate(string $api_key, string $api_secret, string $base_uri): void {
    // Client service needs to be reinitialized with new credentials in case
    // this is being run as a sub-command.
    // @see https://github.com/acquia/cli/issues/403
    $this->setConnector(new Connector([
      'key' => $api_key,
      'secret' => $api_secret
    ]), $base_uri);
  }

  /**
   * Migrate from storing only a single API key to storing multiple.
   */
  public function migrateLegacyApiKey(): void {
    if ($this->datastoreCloud->get('key')
      && $this->datastoreCloud->get('secret')
      && !$this->datastoreCloud->get('acli_key')
      && !$this->datastoreCloud->get('keys')
    ) {
      $uuid = $this->datastoreCloud->get('key');
      $token_info = $this->getClient()->request('get', "/account/tokens/{$uuid}");
      $keys[$uuid] = [
        'label' => $token_info->label,
        'uuid' => $uuid,
        'secret' => $this->datastoreCloud->get('secret'),
      ];
      $this->datastoreCloud->set('keys', $keys);
      $this->datastoreCloud->set('acli_key', $uuid);
    }

    // Convert from serialized to un-serialized.
    if ($this->datastoreCloud->get('acli_key')
      && $this->datastoreCloud->get('keys')
      && is_string($this->datastoreCloud->get('keys'))
      && strpos($this->datastoreCloud->get('keys'), 'a:') === 0
    ) {
      $value = Serializer::unserialize($this->datastoreCloud->get('keys'));
      $this->datastoreCloud->set('keys', $value);
      $this->reAuthenticate($this->cloudCredentials->getCloudKey(), $this->cloudCredentials->getCloudSecret(), $this->cloudCredentials->getBaseUri());
    }
  }

}
