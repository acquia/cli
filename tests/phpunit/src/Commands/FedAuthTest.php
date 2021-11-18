<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\GoodByeWorldCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;
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

  protected $orgUuid = 'org_uuid';
  protected $appUuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

  public function setUp($output = NULL): void {
    parent::setUp();
    self::setCloudIdeEnvVars();
    TestBase::setEnvVars([
      'AH_ORGANIZATION_UUID' => $this->orgUuid,
      'AH_APPLICATION_UUID' => $this->appUuid,
    ]);
  }

  public function tearDown(): void {
    parent::tearDown();
    self::unsetCloudIdeEnvVars();
    TestBase::unsetEnvVars([
      'AH_ORGANIZATION_UUID' => $this->orgUuid,
      'AH_APPLICATION_UUID' => $this->appUuid,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(GoodByeWorldCommand::class);
  }

  public function testFedAuth() {
    $cache = CommandBase::getApplicationCache();
    $cache->clear();
    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
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
          throw new IdentityProviderException('Problem', 403, 'yikes');
        }
        // Second time, return response.
        else {
          return $application_response;
        }
      });

    $this->clientServiceProphecy->setOrganizationUuid($this->orgUuid)->shouldBeCalled();
    $this->mockApplicationsRequest();

    $this->executeCommand([], [
      // Select application.
      '0'
    ]);
    self::assertEquals(0, $this->getStatusCode());
    $this->prophet->checkPredictions();
  }

  public function testSetOrgUuid() {
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'accessToken' => NULL,
      ]);
    $clientService = new ClientService($connector_factory, $this->application);
    $clientService->setOrganizationUuid($this->orgUuid);
    $client = $clientService->getClient();
    $options = $client->getOptions();
    $this->assertArrayHasKey('headers', $options);
    $this->assertArrayHasKey('User-Agent', $options['headers']);
    $query = $client->getQuery();
    $this->assertArrayHasKey('scope', $query);
    $this->assertEquals('organization:' . $this->orgUuid, $query['scope']);

    $this->prophet->checkPredictions();
  }

}
