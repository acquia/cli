<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Command\TelemetryCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class TelemetryCommandTest.
 *
 * @property \Acquia\Cli\Command\TelemetryCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class TelemetryCommandTest extends CommandTestBase {

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
    return $this->injectCommand(TelemetryCommand::class);
  }

  /**
   * Tests the 'telemetry' command.
   */
  public function testTelemetryCommand(): void {
    $account = json_decode(file_get_contents(Path::join($this->fixtureDir, '/account.json')));
    $this->clientProphecy->request('get', '/account')
      ->willReturn($account);

    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Telemetry has been enabled.', $output);
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Telemetry has been disabled.', $output);
  }

  public function providerTestTelemetryPrompt(): array {
    return [
      [
        // Would you like to share anonymous performance usage and data?
        ['n'],
        'Ok, no data will be collected and shared with us.',
      ],
    ];
  }

  /**
   * Tests telemetry prompt.
   *
   * @dataProvider providerTestTelemetryPrompt
   *
   * @param array $inputs
   * @param $message
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testTelemetryPrompt(array $inputs, $message): void {
    $this->command = $this->injectCommand(LinkCommand::class);
    $this->cloudConfig = [DataStoreContract::SEND_TELEMETRY => NULL];
    $this->createMockConfigFile();
    $this->createMockAcliConfigFile('a47ac10b-58cc-4372-a567-0e02b2c3d470');
    $this->mockApplicationRequest();
    $this->mockAmplitudeRequest();
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();

    $this->assertStringContainsString('Would you like to share anonymous performance usage and data?', $output);
    $this->assertStringContainsString($message, $output);
  }

  /**
   * Opted out by default.
   * @throws \Exception
   */
  public function testAmplitudeDisabled(): void {
    $this->cloudConfig = [DataStoreContract::SEND_TELEMETRY => FALSE];
    $this->createMockConfigFile();
    $this->amplitudeProphecy->setOptOut(TRUE)->shouldBeCalled();
    $this->amplitudeProphecy->queueEvent('Ran command', Argument::type('array'))->shouldBeCalled();
    $this->executeCommand();

    $this->assertEquals(0, $this->getStatusCode());
    $this->prophet->checkPredictions();
  }

  public function testAmplitudeEnabled(): void {
    $this->cloudConfig = [DataStoreContract::SEND_TELEMETRY => TRUE];
    $this->createMockConfigFile();

    $this->mockAmplitudeRequest();
    $this->executeCommand();

    $this->prophet->checkPredictions();
    $this->assertEquals(0, $this->getStatusCode());
  }

  public function testMigrateLegacyTelemetryPreference(): void {
    $this->cloudConfig = [DataStoreContract::SEND_TELEMETRY => NULL];
    $this->createMockConfigFile();
    $this->fs->remove($this->legacyAcliConfigFilepath);
    $legacy_acli_config = ['send_telemetry' => TRUE];
    $contents = json_encode($legacy_acli_config);
    $this->fs->dumpFile($this->legacyAcliConfigFilepath, $contents);
    $this->mockAmplitudeRequest();
    $this->executeCommand();
    $this->prophet->checkPredictions();
    $this->assertEquals(0, $this->getStatusCode());
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

  protected function mockAmplitudeRequest(): void {
    $account = json_decode(file_get_contents(Path::join($this->fixtureDir, '/account.json')));
    $this->clientProphecy->request('get', '/account')->willReturn($account)->shouldBeCalled();

    $this->amplitudeProphecy->queueEvent('Ran command', Argument::type('array'))->shouldBeCalled();
    $this->amplitudeProphecy->init(Argument::type('string'))->shouldBeCalled();
    $this->amplitudeProphecy->setUserProperties(Argument::type('array'))->shouldBeCalled();
    $this->amplitudeProphecy->setDeviceId(Argument::type('string'))->shouldBeCalled();
    $this->amplitudeProphecy->setUserId(Argument::type('string'))->shouldBeCalled();
    $this->amplitudeProphecy->setOptOut(FALSE)->shouldBeCalled();
    $this->amplitudeProphecy->logQueuedEvents()->shouldBeCalled();
    // Ensure problems with telemetry reporting are handled silently.
    // This doesn't seem to actually trigger code coverage of the exception catch, why?
    $this->amplitudeProphecy->setUserId()->willThrow(new IdentityProviderException('test', 1, 'test'));
  }

}
