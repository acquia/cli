<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\AccessTokenConnector;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Connector\ConnectorInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;

/**
 * Class ClientServiceTest.
 */
class ClientServiceTest extends TestBase {

  public function testSetOrgUuid() {
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'accessToken' => NULL,
      ]);
    $clientService = new ClientService($connector_factory, $this->application);
    $clientService->setOrganizationUuid('org_uuid');
    $client = $clientService->getClient();
    $options = $client->getOptions();
    $this->assertArrayHasKey('headers', $options);
    $this->assertArrayHasKey('User-Agent', $options['headers']);
    $query = $client->getQuery();
    $this->assertArrayHasKey('scope', $query);
    $this->assertEquals('organization:org_uuid', $query['scope']);

    $this->prophet->checkPredictions();
  }

}
