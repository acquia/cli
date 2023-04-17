<?php

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
   * @return array[]
   */
  public function providerTestAuthLogoutCommand(): array {
    return [
      // Data set 0.
      [
        // $machine_is_authenticated
        FALSE,
        // $inputs
        [],
      ],
      // Data set 1.
      [
        // $machine_is_authenticated
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
   * Tests the 'auth:login' command.
   *
   * @dataProvider providerTestAuthLogoutCommand
   * @param array $inputs
   * @param array $config
   */
  public function testAcsfAuthLogoutCommand(bool $machine_is_authenticated, array $inputs, array $config = []): void {
    if (!$machine_is_authenticated) {
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
