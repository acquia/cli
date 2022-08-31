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
    ];
  }

  /**
   * @dataProvider providerTestIsMachineAuthenticated
   * @param array $env_vars
   * @param bool $is_authenticated
   */
  public function testIsMachineAuthenticated(array $env_vars, bool $is_authenticated): void {
    self::setEnvVars($env_vars);
    $cloud_datastore = $this->prophet->prophesize(CloudDataStore::class);
    $client_service = new ClientService(new ConnectorFactory(['key' => NULL, 'secret' => NULL, 'accessToken' => NULL]), $this->prophet->prophesize(Application::class)->reveal(), new CloudCredentials($cloud_datastore->reveal()));
    $this->assertEquals($is_authenticated, $client_service->isMachineAuthenticated($cloud_datastore->reveal()));
    self::unsetEnvVars($env_vars);
  }

}
