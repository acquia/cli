<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;

/**
 * Class CloudServiceTest.
 */
class CloudServiceTest extends TestBase {

  /**
   * @return array[]
   */
  public function providerTestIsMachineAuthenticated(): array {
    return [
      [
        ['ACLI_ACCESS_TOKEN' => 'token', 'ACLI_KEY' => 'key', 'ACLI_SECRET' => 'secret'],
        TRUE,
      ],
      [
        ['ACLI_ACCESS_TOKEN' => NULL, 'ACLI_KEY' => 'key', 'ACLI_SECRET' => 'secret'],
        TRUE,
      ],
      [
        ['ACLI_ACCESS_TOKEN' => NULL, 'ACLI_KEY' => NULL, 'ACLI_SECRET' => NULL],
        FALSE,
      ],
      [
        ['ACLI_ACCESS_TOKEN' => NULL, 'ACLI_KEY' => 'key', 'ACLI_SECRET' => NULL],
        FALSE,
      ]
    ];
  }

  /**
   * @dataProvider providerTestIsMachineAuthenticated
   *
   * @param array $env_vars
   * @param bool $is_authenticated
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function testIsMachineAuthenticated(array $env_vars, bool $is_authenticated): void {
    self::setEnvVars($env_vars);
    $cloud_datastore = $this->prophet->prophesize(CloudDataStore::class);
    $cloudCredentials = new CloudCredentials($cloud_datastore->reveal());
    $client_service = new ClientService(new ConnectorFactory($cloudCredentials), $this->prophet->prophesize(Application::class)->reveal(), $cloudCredentials);
    $this->assertEquals($is_authenticated, $client_service->isMachineAuthenticated());
    self::unsetEnvVars($env_vars);
  }

}
