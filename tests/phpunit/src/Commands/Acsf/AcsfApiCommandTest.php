<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\AcsfApi\AcsfClient;
use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\Acsf\AcsfApiBaseCommand;
use Acquia\Cli\Command\Acsf\AcsfCommandFactory;
use Acquia\Cli\CommandFactoryInterface;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @property \Acquia\Cli\Command\Api\ApiBaseCommand $command
 */
class AcsfApiCommandTest extends AcsfCommandTestBase {

  protected string $apiSpecFixtureFilePath = __DIR__ . '/../../../../../assets/acsf-spec.yaml';
  protected string $apiCommandPrefix = 'acsf';

  public function setUp(): void {
    parent::setUp();
    $this->clientProphecy->addOption('headers', ['Accept' => 'application/json']);
    putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');
  }

  protected function createCommand(): Command {
    $this->createMockCloudConfigFile($this->getAcsfCredentialsFileContents());
    $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
    $this->setClientProphecies(AcsfClientService::class);
    return $this->injectCommand(AcsfApiBaseCommand::class);
  }

  public function testAcsfCommandExecutionForHttpPostWithMultipleDataTypes(): void {
    $mockBody = $this->getMockResponseFromSpec('/api/v1/groups/{group_id}/members', 'post', '200');
    $this->clientProphecy->request('post', '/api/v1/groups/1/members')->willReturn($mockBody)->shouldBeCalled();
    $this->clientProphecy->addOption('json', ["uids" => ["1", "2", "3"]])->shouldBeCalled();
    $this->command = $this->getApiCommandByName('acsf:groups:add-members');
    $this->executeCommand([
      'uids' => '1,2,3',
    ], [
      // group_id
      '1',
    ]);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
  }

  public function testAcsfCommandExecutionBool(): void {
    $mockBody = $this->getMockResponseFromSpec('/api/v1/update/pause', 'post', '200');
    $this->clientProphecy->request('post', '/api/v1/update/pause')->willReturn($mockBody)->shouldBeCalled();
    $this->clientProphecy->addOption('json', ["pause" => TRUE])->shouldBeCalled();
    $this->command = $this->getApiCommandByName('acsf:updates:pause');
    $this->executeCommand([], [
      // pause
      '1',
    ]);

    // Assert.
    $this->prophet->checkPredictions();
  }

  public function testAcsfCommandExecutionForHttpGet(): void {
    $mockBody = $this->getMockResponseFromSpec('/api/v1/audit', 'get', '200');
    $this->clientProphecy->addQuery('limit', '1')->shouldBeCalled();
    $this->clientProphecy->request('get', '/api/v1/audit')->willReturn($mockBody)->shouldBeCalled();
    $this->command = $this->getApiCommandByName('acsf:info:audit-events-find');
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

  /**
   * @return array<mixed>
   */
  public function providerTestAcsfCommandExecutionForHttpGetMultiple(): array {
    return [
      ['get', '/api/v1/audit', '/api/v1/audit', 'acsf:info:audit-events-find', [], []],
      ['post', '/api/v1/sites', '/api/v1/sites', 'acsf:sites:create', ['site_name' => 'foobar', '--stack_id' => '1', '--group_ids' => ['91,81']], ['site_name' => 'foobar', 'stack_id' => '1', 'group_ids' => [91, 81]]],
      ['post', '/api/v1/sites', '/api/v1/sites', 'acsf:sites:create', ['site_name' => 'foobar', '--stack_id' => '1', '--group_ids' => ['91','81']], ['site_name' => 'foobar', 'stack_id' => '1', 'group_ids' => [91, 81]]],
      ['post', '/api/v1/sites/{site_id}/backup', '/api/v1/sites/1/backup', 'acsf:sites:backup', ['--label' => 'foo', 'site_id' => '1'], ['label' => 'foo']],
      ['post', '/api/v1/groups/{group_id}/members', '/api/v1/groups/2/members', 'acsf:groups:add-members', ['group_id' => '2', 'uids' => '1'], ['group_id' => 'foo', 'uids' => 1]],
      ['post', '/api/v1/groups/{group_id}/members', '/api/v1/groups/2/members', 'acsf:groups:add-members', ['group_id' => '2', 'uids' => '1,3'], ['group_id' => 'foo', 'uids' => [1, 3]]],
    ];
  }

  /**
   * @dataProvider providerTestAcsfCommandExecutionForHttpGetMultiple
   */
  public function testAcsfCommandExecutionForHttpGetMultiple(mixed $method, mixed $specPath, mixed $path, mixed $command, mixed $arguments = [], mixed $jsonArguments = []): void {
    $mockBody = $this->getMockResponseFromSpec($specPath, $method, '200');
    $this->clientProphecy->request($method, $path)->willReturn($mockBody)->shouldBeCalled();
    foreach ($jsonArguments as $argumentName => $value) {
      $this->clientProphecy->addOption('json', [$argumentName => $value]);
    }
    $this->command = $this->getApiCommandByName($command);
    $this->executeCommand($arguments, []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertNotNull($output);
    $this->assertJson($output);
    $contents = json_decode($output, TRUE);
  }

  protected function setClientProphecies(?string $clientServiceClass = ClientService::class): void {
    $this->clientProphecy = $this->prophet->prophesize(AcsfClient::class);
    $this->clientProphecy->addOption('headers', ['User-Agent' => 'acli/UNKNOWN']);
    $this->clientProphecy->addOption('debug', Argument::type(OutputInterface::class));
    $this->clientServiceProphecy = $this->prophet->prophesize($clientServiceClass);
    $this->clientServiceProphecy->getClient()
      ->willReturn($this->clientProphecy->reveal());
    $this->clientServiceProphecy->isMachineAuthenticated()
      ->willReturn(TRUE);
  }

  protected function getCommandFactory(): CommandFactoryInterface {
    return new AcsfCommandFactory(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->projectDir,
      $this->clientServiceProphecy->reveal(),
      $this->logStreamManagerProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClientProphecy->reveal()
    );
  }

}
