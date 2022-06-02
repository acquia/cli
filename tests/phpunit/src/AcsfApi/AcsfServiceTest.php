<?php

namespace Acquia\Cli\Tests\AcsfApi;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfConnectorFactory;
use Acquia\Cli\Application;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;

/**
 * Class CloudServiceTest.
 */
class AcsfServiceTest extends TestBase {

  /**
   * @return array[]
   */
  public function providerTestIsMachineAuthenticated(): array {
    return [
      [
        ['ACSF_USERNAME' => 'key', 'ACSF_KEY' => 'secret'],
        TRUE,
      ],
      [
        ['ACSF_USERNAME' => 'key', 'ACSF_KEY' => 'secret'],
        TRUE,
      ],
      [
        ['ACSF_USERNAME' => NULL, 'ACSF_KEY' => NULL],
        FALSE,
      ],
      [
        ['ACSF_USERNAME' => 'key', 'ACSF_KEY' => NULL],
        FALSE,
      ],
    ];
  }

  /**
   * @dataProvider providerTestIsMachineAuthenticated
   * @param array $env_vars
   * @param bool $is_authenticated
   */
  public function testIsMachineAuthenticated(array $env_vars, bool $is_authenticated) {
    self::setEnvVars($env_vars);
    $client_service = new AcsfClientService(new AcsfConnectorFactory(['key' => NULL, 'secret' => NULL]), $this->prophet->prophesize(Application::class)->reveal());
    $cloud_datastore = $this->prophet->prophesize(CloudDataStore::class);
    $this->assertEquals($is_authenticated, $client_service->isMachineAuthenticated($cloud_datastore->reveal()));
    self::unsetEnvVars($env_vars);
  }

}
