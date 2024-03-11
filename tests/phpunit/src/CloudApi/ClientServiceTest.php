<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\CloudApi\ConnectorFactory;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Tests\TestBase;

class ClientServiceTest extends TestBase {

  /**
   * @return array<mixed>
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
      ],
    ];
  }

  /**
   * @dataProvider providerTestIsMachineAuthenticated
   */
  public function testIsMachineAuthenticated(array $envVars, bool $isAuthenticated): void {
    self::setEnvVars($envVars);
    $cloudDatastore = $this->prophet->prophesize(CloudDataStore::class);
    $clientService = new ClientService(new ConnectorFactory(['key' => NULL, 'secret' => NULL, 'accessToken' => NULL]), $this->application, new CloudCredentials($cloudDatastore->reveal()));
    $this->assertEquals($isAuthenticated, $clientService->isMachineAuthenticated());
    self::unsetEnvVars($envVars);
  }

}
