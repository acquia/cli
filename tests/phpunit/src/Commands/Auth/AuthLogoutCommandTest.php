<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\Command\Auth\AuthLogoutCommand;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property AuthLogoutCommandTest $command
 */
class AuthLogoutCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(AuthLogoutCommand::class);
  }

  public function testAuthLogoutCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertFileExists($this->cloudConfigFilepath);
    $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
    $this->assertFalse($config->exists('acli_key'));
    $this->assertEmpty($config->get('keys'));
    $this->assertStringContainsString('The active Acquia Cloud API credentials were deleted', $output);
  }

  public function testAuthLogoutCommandNotAuthenticated(): void {
    $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
    $this->removeMockCloudConfigFile();

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('You are not authenticated and therefore cannot logout');
    $this->executeCommand();
  }

  public function testAuthLogoutCommandNoDeleteArg(): void {
    $this->executeCommand(['--no-delete' => TRUE]);
    $output = $this->getDisplay();
    $this->assertFileExists($this->cloudConfigFilepath);
    $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
    $this->assertFalse($config->exists('acli_key'));
    $this->assertNotEmpty($config->get('keys'));
    $this->assertStringContainsString('The active Acquia Cloud API credentials were unset', $output);
  }

  public function testAuthLogoutCommandNoDeleteInput(): void {
    $this->executeCommand([], ['n']);
    $output = $this->getDisplay();
    $this->assertFileExists($this->cloudConfigFilepath);
    $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
    $this->assertFalse($config->exists('acli_key'));
    $this->assertNotEmpty($config->get('keys'));
    $this->assertStringContainsString('The active Acquia Cloud API credentials were unset', $output);
  }

}
