<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\TelemetryCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\AccountResponse;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Validator\Constraints\Uuid;
use Webmozart\PathUtil\Path;

/**
 * Class TelemetryCommandTest.
 *
 * @property \Acquia\Cli\Command\TelemetryCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class TelemetryCommandTest extends CommandTestBase {

  /**
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
   */
  public function testTelemetryPrompt(array $inputs, $message): void {
    $this->cloudConfig = [DataStoreContract::SEND_TELEMETRY => NULL];
    $this->createMockConfigFile();

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
    $this->acliConfig = [DataStoreContract::SEND_TELEMETRY => TRUE];
    $this->createMockConfigFile();

    $this->mockAmplitudeRequest();
    $this->executeCommand();
    $this->prophet->checkPredictions();
    $this->assertEquals(0, $this->getStatusCode());
  }

  public function testMigrateLegacyTelemetryPreference() {
    $legacy_acli_config_filepath = Path::join($this->dataDir, 'acquia-cli.json');
    $this->fs->remove($legacy_acli_config_filepath);
    $legacy_acli_config = ['send_telemetry' => TRUE];
    $contents = json_encode($legacy_acli_config);
    $this->fs->dumpFile($legacy_acli_config_filepath, $contents);
    $this->mockAmplitudeRequest();
    $this->executeCommand();
    $this->prophet->checkPredictions();
    $this->assertEquals(0, $this->getStatusCode());
    $this->fs->remove($legacy_acli_config_filepath);
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
