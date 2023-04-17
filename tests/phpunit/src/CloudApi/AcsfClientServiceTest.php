<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfConnectorFactory;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;

class AcsfClientServiceTest extends TestBase {

  /**
   * @return array[]
   */
  public function providerTestIsMachineAuthenticated(): array {
    return [
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
   * @param array $env_vars
   */
  public function testIsMachineAuthenticated(array $env_vars, bool $is_authenticated): void {
    self::setEnvVars($env_vars);
    $cloud_datastore = $this->prophet->prophesize(CloudDataStore::class);
    $client_service = new AcsfClientService(new AcsfConnectorFactory(['key' => NULL, 'secret' => NULL, 'accessToken' => NULL]), $this->application, new AcsfCredentials($cloud_datastore->reveal()));
    $this->assertEquals($is_authenticated, $client_service->isMachineAuthenticated());
    $client_service->getClient();
    self::unsetEnvVars($env_vars);
  }

}
