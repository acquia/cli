<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\App\UnlinkCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Exception;
use Symfony\Component\Console\Command\Command;

/**
 * Class UnlinkCommandTest.
 *
 * @property \Acquia\Cli\Command\App\UnlinkCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class UnlinkCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(UnlinkCommand::class);
  }

  /**
   * Tests the 'unlink' command.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testUnlinkCommand(): void {
    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $cloud_application = $applications_response->{'_embedded'}->items[0];
    $cloud_application_uuid = $cloud_application->uuid;
    $this->createMockAcliConfigFile($cloud_application_uuid);
    $this->mockApplicationRequest();

    // Assert we set it correctly.
    $this->assertEquals($applications_response->{'_embedded'}->items[0]->uuid, $this->datastoreAcli->get('cloud_app_uuid'));

    $this->executeCommand([], []);
    $output = $this->getDisplay();

    // Assert it's been unset.
    $this->assertNull($this->datastoreAcli->get('cloud_app_uuid'));
    $this->assertStringContainsString("Unlinked {$this->projectFixtureDir} from Cloud application " . $cloud_application->name, $output);
  }

  public function testUnlinkCommandInvalidDir(): void {
    try {
      $this->executeCommand([], []);
    }
    catch (Exception $exception) {
      $this->assertStringContainsString('There is no Cloud Platform application linked to', $exception->getMessage());
    }
  }

}
