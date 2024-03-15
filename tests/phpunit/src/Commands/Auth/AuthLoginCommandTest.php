<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\Command\Auth\AuthLoginCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Connector;
use Generator;
use Prophecy\Argument;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property AuthLoginCommand $command
 */
class AuthLoginCommandTest extends CommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(AuthLoginCommand::class);
  }

  public function testAuthLoginCommand(): void {
    $this->mockRequest('getAccount');
    $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))->shouldBeCalled();
    $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
    $this->removeMockCloudConfigFile();
    $this->createDataStores();
    $this->command = $this->createCommand();

    $this->executeCommand(['--key' => $this->key, '--secret' => $this->secret]);
    $output = $this->getDisplay();

    $this->assertStringContainsString('Saved credentials', $output);
    $this->assertKeySavedCorrectly();
  }

  public function providerTestAuthLoginInvalidInputCommand(): Generator {
    yield
      [
        [],
        ['--key' => 'no spaces are allowed' , '--secret' => $this->secret],
      ];
    yield
      [
        [],
        ['--key' => 'shorty' , '--secret' => $this->secret],
      ];
    yield
      [
        [],
        ['--key' => ' ', '--secret' => $this->secret],
      ];
  }

  /**
   * @dataProvider providerTestAuthLoginInvalidInputCommand
   */
  public function testAuthLoginInvalidInputCommand(array $inputs, array $args): void {
    $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
    $this->removeMockCloudConfigFile();
    $this->createDataStores();
    $this->command = $this->createCommand();
    $this->expectException(ValidatorException::class);
    $this->executeCommand($args, $inputs);
  }

  public function testAuthLoginInvalidDatastore(): void {
    $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
    $this->removeMockCloudConfigFile();
    $this->createDataStores();
    $this->datastoreCloud->set('keys', ['key1']);
    $this->datastoreCloud->set('acli_key', 'key2');
    $this->command = $this->createCommand();
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Invalid key in Cloud datastore; run acli auth:logout && acli auth:login to fix');
    $this->executeCommand();
  }

  protected function assertInteractivePrompts(string $output): void {
    // Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?
    $this->assertStringContainsString('You will need a Cloud Platform API token from https://cloud.acquia.com/a/profile/tokens', $output);
    $this->assertStringContainsString('Do you want to open this page to generate a token now?', $output);
    $this->assertStringContainsString('Enter your Cloud API key (option -k, --key):', $output);
    $this->assertStringContainsString('Enter your Cloud API secret (option -s, --secret) (input will be hidden):', $output);
  }

  protected function assertKeySavedCorrectly(): void {
    $credsFile = $this->cloudConfigFilepath;
    $this->assertFileExists($credsFile);
    $config = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $credsFile);
    $this->assertTrue($config->exists('acli_key'));
    $this->assertEquals($this->key, $config->get('acli_key'));
    $this->assertTrue($config->exists('keys'));
    $keys = $config->get('keys');
    $this->assertArrayHasKey($this->key, $keys);
    $this->assertArrayHasKey('label', $keys[$this->key]);
    $this->assertArrayHasKey('secret', $keys[$this->key]);
    $this->assertEquals($this->secret, $keys[$this->key]['secret']);
  }

}
