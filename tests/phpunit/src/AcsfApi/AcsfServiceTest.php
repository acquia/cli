<?php

namespace Acquia\Cli\Tests\AcsfApi;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfConnectorFactory;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Application;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ClientServiceTest.
 */
class AcsfServiceTest extends TestBase {

  protected function setUp(OutputInterface $output = NULL): void {
    parent::setUp($output);
    $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);

  }

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
   */
  public function testIsMachineAuthenticated(array $env_vars, bool $is_authenticated): void {
    self::setEnvVars($env_vars);
    $client_service = new AcsfClientService(new AcsfConnectorFactory(['key' => NULL, 'secret' => NULL]), $this->prophet->prophesize(Application::class)->reveal(), $this->cloudCredentials);
    $this->assertEquals($is_authenticated, $client_service->isMachineAuthenticated());
    self::unsetEnvVars($env_vars);
  }

}
