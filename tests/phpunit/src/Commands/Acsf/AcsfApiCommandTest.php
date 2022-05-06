<?php

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Command\Acsf\AcsfApiBaseCommand;
use Acquia\Cli\Command\Acsf\AcsfCommandFactory;
use Acquia\Cli\CommandFactoryInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Class ApiCommandTest.
 *
 * @property \Acquia\Cli\Command\Api\ApiBaseCommand $command
 * @package Acquia\Cli\Tests\Api
 */
class AcsfApiCommandTest extends AcsfCommandTestBase {

  protected $apiSpecFixtureFilePath = __DIR__ . '/../../../../../assets/acsf-spec.yaml';
  protected string $apiCommandPrefix = 'acsf';

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->clientProphecy->addOption('headers', ['Accept' => 'application/json']);
    putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    $this->createMockCloudConfigFile($this->getAcsfCredentialsFileContents());
    $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
    $this->setClientProphecies(AcsfClientService::class);
    return $this->injectCommand(AcsfApiBaseCommand::class);
  }

  public function testAcsfCommandExecutionForHttpGet(): void {
    $mock_body = $this->getMockResponseFromSpec('/api/v1/audit', 'get', '200');
    $this->clientProphecy->addQuery('limit', '1')->shouldBeCalled();
    $this->clientProphecy->request('get', '/api/v1/audit')->willReturn($mock_body)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('acsf:audit-events:find');
    // Our mock Client doesn't actually return a limited dataset, but we still assert it was passed added to the
    // client's query correctly.
    $this->executeCommand(['--limit' => '1']);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertNotNull($output);
    $this->assertJson($output);
    $contents = json_decode($output, TRUE);
    $this->assertArrayHasKey('count', $contents);
  }

  protected function getCommandFactory(): CommandFactoryInterface {
    return new AcsfCommandFactory(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->projectFixtureDir,
      $this->clientServiceProphecy->reveal(),
      $this->logStreamManagerProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir,
      $this->logger
    );
  }

}
