<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\AcsfApi;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfConnectorFactory;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Application;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Output\OutputInterface;

class AcsfServiceTest extends TestBase {

  protected function setUp(OutputInterface $output = NULL): void {
    parent::setUp($output);
    $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);

  }

  /**
   * @return array<mixed>
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
   * @param array $envVars
   */
  public function testIsMachineAuthenticated(array $envVars, bool $isAuthenticated): void {
    self::setEnvVars($envVars);
    $clientService = new AcsfClientService(new AcsfConnectorFactory(['key' => NULL, 'secret' => NULL]), $this->prophet->prophesize(Application::class)->reveal(), $this->cloudCredentials);
    $this->assertEquals($isAuthenticated, $clientService->isMachineAuthenticated());
    self::unsetEnvVars($envVars);
  }

}
