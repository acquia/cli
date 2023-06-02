<?php

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfConnectorFactory;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;

class AcsfClientServiceTest extends TestBase {

  /**
   * @return array<mixed>
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
      ],
    ];
  }

  /**
   * @dataProvider providerTestIsMachineAuthenticated
   * @param array $envVars
   */
  public function testIsMachineAuthenticated(array $envVars, bool $isAuthenticated): void {
    self::setEnvVars($envVars);
    $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
    $clientService = new AcsfClientService(new AcsfConnectorFactory(['key' => NULL, 'secret' => NULL, 'accessToken' => NULL]), $this->application, new AcsfCredentials($cloudDatastore->reveal()));
    $this->assertEquals($isAuthenticated, $clientService->isMachineAuthenticated());
    $clientService->getClient();
    self::unsetEnvVars($envVars);
  }

}
