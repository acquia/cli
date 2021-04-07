<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Command\TelemetryCommand;
use Acquia\Cli\Command\TelemetryDisableCommand;
use Acquia\Cli\Command\TelemetryEnableCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class TelemetryEnableCommandTest.
 *
 * @property \Acquia\Cli\Command\TelemetryEnableCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class TelemetryEnableCommandTest extends CommandTestBase {

  /**
   * @var string
   */
  protected $legacyAcliConfigFilepath;

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->legacyAcliConfigFilepath = Path::join($this->dataDir, 'acquia-cli.json');
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

  /**b
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(TelemetryEnableCommand::class);
  }

  /**
   * Tests the 'telemetry:disable' command.
   */
  public function testTelemetryCommand(): void {
    $account = json_decode(file_get_contents(Path::join($this->fixtureDir, '/account.json')));
    $this->clientProphecy->request('get', '/account')
      ->willReturn($account);

    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Telemetry has been enabled.', $output);

    // @todo Don't actually change the fixture during the test! Copy to temp dir.
    $settings = json_decode(file_get_contents($this->cloudConfigFilepath), TRUE);
    $this->assertTrue($settings['send_telemetry']);
  }

}
