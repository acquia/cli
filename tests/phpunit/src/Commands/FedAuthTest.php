<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\GoodByeWorldCommand;
use Acquia\Cli\Tests\CloudApi\AccessTokenConnectorTrait;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Prophecy\Argument\ArgumentsWildcard;
use Symfony\Component\Console\Command\Command;

/**
 * Class FedAuthTest.
 *
 * @package Acquia\Cli\Tests\Commands
 * @property \Acquia\Cli\Command\GoodByeWorldCommand $command
 */
class FedAuthTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(GoodByeWorldCommand::class);
  }

  public function testFedAuth() {
    AccessTokenConnectorTrait::setAccessTokenEnvVars();
    TestBase::setEnvVars(['AH_APPLICATION_UUID' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470']);
    $cache = CommandBase::getApplicationCache();
    $cache->clear();
    $applications_response = $this->getMockResponseFromSpec('/applications', 'get', '200');
    $application_response = $applications_response->{'_embedded'}->items[0];

    // We use this tricky code bit because we need this method to throw an
    // exception the first time that we call it and return a response the
    // second time.
    // @see https://github.com/phpspec/prophecy/issues/213
    $this->clientProphecy->request('get',
      '/applications/' . $applications_response->{'_embedded'}->items[0]->uuid)
      ->will(function ($args, $mock, $methodProphecy) use ($application_response) {
        $methodCalls = $mock->findProphecyMethodCalls(
          $methodProphecy->getMethodName(),
          new ArgumentsWildcard($args)
        );
        // First time, throw error.
        if (count($methodCalls) === 0) {
          throw new ApiErrorException((object) ['message' => 'yikes', 'error' => 'yikes'], 'yikes', 403);
        }
        // Second time, return response.
        else {
          return $application_response;
        }
      });
    $this->mockApplicationsRequest();
    $this->clientServiceProphecy->recreateConnector()->ShouldBeCalled();

    $this->executeCommand([], [
      // Select application.
      '0'
    ]);
    self::assertEquals(0, $this->getStatusCode());
    $this->prophet->checkPredictions();
    AccessTokenConnectorTrait::unsetAccessTokenEnvVars();
    TestBase::unsetEnvVars(['AH_APPLICATION_UUID' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470']);
  }

  public function testSetOrgUuid() {
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'accessToken' => NULL,
      ]);
    $clientService = new ClientService($connector_factory, $this->application);
    $clientService->recreateConnector();
    $client = $clientService->getClient();
    $options = $client->getOptions();
    $this->assertArrayHasKey('headers', $options);
    $this->assertArrayHasKey('User-Agent', $options['headers']);

    $this->prophet->checkPredictions();
  }

}
