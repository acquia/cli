<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Command\Acsf\AcsfApiAuthLogoutCommand;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Symfony\Component\Console\Command\Command;

/**
 * @property AcsfAuthLogoutCommandTest $command
 */
class AcsfAuthLogoutCommandTest extends AcsfCommandTestBase {

  protected function createCommand(): Command {
    $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
    return $this->injectCommand(AcsfApiAuthLogoutCommand::class);
  }

  /**
   * @return array<mixed>
   */
  public function providerTestAuthLogoutCommand(): array {
    return [
      // Data set 0.
      [
        // $machineIsAuthenticated
        FALSE,
        // $inputs
        [],
      ],
      // Data set 1.
      [
        // $machineIsAuthenticated
        TRUE,
        // $inputs
        [
          // Choose a Factory to logout of
          0,
        ],
        // $config.
        $this->getAcsfCredentialsFileContents(),
      ],
    ];
  }

  /**
   * @dataProvider providerTestAuthLogoutCommand
   * @param array $inputs
   * @param array $config
   */
  public function testAcsfAuthLogoutCommand(bool $machineIsAuthenticated, array $inputs, array $config = []): void {
    if (!$machineIsAuthenticated) {
      $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
      $this->removeMockCloudConfigFile();
    }
    else {
      $this->createMockCloudConfigFile($config);
    }

    $this->createDataStores();
    $this->command = $this->createCommand();
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    // Assert creds are removed locally.
    $this->assertFileExists($this->cloudConfigFilepath);
    $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
    $this->assertFalse($config->exists('acli_key'));
    $this->assertNull($config->get('acsf_active_factory'));
  }

}
