<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\AcsfApi\AcsfClient;
use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\Command\Acsf\AcsfCommandFactory;
use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\CommandFactoryInterface;
use Acquia\Cli\Exception\AcquiaCliException;
use Prophecy\Argument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @property \Acquia\Cli\Command\Api\ApiBaseCommand $command
 */
class AcsfApiCommandTest extends AcsfCommandTestBase {

  protected string $apiSpecFixtureFilePath = __DIR__ . '/../../../../../assets/acsf-spec.yaml';
  protected string $apiCommandPrefix = 'acsf';

  public function setUp(): void {
    parent::setUp();
    $this->clientProphecy->addOption('headers', ['Accept' => 'application/hal+json, version=2']);
    putenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE=1');
  }

  protected function createCommand(): CommandBase {
    $this->createMockCloudConfigFile($this->getAcsfCredentialsFileContents());
    $this->cloudCredentials = new AcsfCredentials($this->datastoreCloud);
    $this->setClientProphecies();
    return $this->injectCommand(ApiBaseCommand::class);
  }

  public function testAcsfCommandExecutionForHttpPostWithMultipleDataTypes(): void {
    $mockBody = $this->getMockResponseFromSpec('/api/v1/groups/{group_id}/members', 'post', '200');
    $this->clientProphecy->request('post', '/api/v1/groups/1/members')->willReturn($mockBody)->shouldBeCalled();
    $this->clientProphecy->addOption('json', ["uids" => ["1", "2", "3"]])->shouldBeCalled();
    $this->command = $this->getApiCommandByName('acsf:groups:add-members');
    $this->executeCommand([
      'uids' => '1,2,3',
    ], [
      // group_id.
      '1',
    ]);

    // Assert.
    $output = $this->getDisplay();
  }

  public function testAcsfCommandExecutionBool(): void {
    $mockBody = $this->getMockResponseFromSpec('/api/v1/update/pause', 'post', '200');
    $this->clientProphecy->request('post', '/api/v1/update/pause')->willReturn($mockBody)->shouldBeCalled();
    $this->clientProphecy->addOption('json', ["pause" => TRUE])->shouldBeCalled();
    $this->command = $this->getApiCommandByName('acsf:updates:pause');
    $this->executeCommand([], [
      // Pause.
      '1',
    ]);

    // Assert.
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
      ['post', '/api/v1/sites', '/api/v1/sites', 'acsf:sites:create', ['site_name' => 'foobar', '--stack_id' => '1', 'group_ids' => ['91,81']], ['site_name' => 'foobar', 'stack_id' => '1', 'group_ids' => [91, 81]]],
      ['post', '/api/v1/sites', '/api/v1/sites', 'acsf:sites:create', ['site_name' => 'foobar', '--stack_id' => '1', 'group_ids' => ['91','81']], ['site_name' => 'foobar', 'stack_id' => '1', 'group_ids' => [91, 81]]],
      ['post', '/api/v1/sites/{site_id}/backup', '/api/v1/sites/1/backup', 'acsf:sites:backup', ['--label' => 'foo', 'site_id' => '1'], ['label' => 'foo']],
      ['post', '/api/v1/groups/{group_id}/members', '/api/v1/groups/2/members', 'acsf:groups:add-members', ['group_id' => '2', 'uids' => '1'], ['group_id' => 'foo', 'uids' => 1]],
      ['post', '/api/v1/groups/{group_id}/members', '/api/v1/groups/2/members', 'acsf:groups:add-members', ['group_id' => '2', 'uids' => '1,3'], ['group_id' => 'foo', 'uids' => [1, 3]]],
    ];
  }

  /**
   * @dataProvider providerTestAcsfCommandExecutionForHttpGetMultiple
   */
  public function testAcsfCommandExecutionForHttpGetMultiple(string $method, string $specPath, string $path, string $command, array $arguments = [], array $jsonArguments = []): void {
    $mockBody = $this->getMockResponseFromSpec($specPath, $method, '200');
    $this->clientProphecy->request($method, $path)->willReturn($mockBody)->shouldBeCalled();
    foreach ($jsonArguments as $argumentName => $value) {
      $this->clientProphecy->addOption('json', [$argumentName => $value]);
    }
    $this->command = $this->getApiCommandByName($command);
    $this->executeCommand($arguments);

    // Assert.
    $output = $this->getDisplay();
    $this->assertNotNull($output);
    $this->assertJson($output);
    json_decode($output, TRUE);
  }

  public function testAcsfUnauthenticatedFailure(): void {
    $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(FALSE);
    $this->removeMockConfigFiles();

    $inputs = [
      // Would you like to share anonymous performance usage and data?
      'n',
    ];
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('This machine is not yet authenticated with Site Factory.');
    $this->executeCommand([], $inputs);
  }

  protected function setClientProphecies(): void {
    $this->clientProphecy = $this->prophet->prophesize(AcsfClient::class);
    $this->clientProphecy->addOption('headers', ['User-Agent' => 'acli/UNKNOWN']);
    $this->clientProphecy->addOption('debug', Argument::type(OutputInterface::class));
    $this->clientServiceProphecy = $this->prophet->prophesize(AcsfClientService::class);
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
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
    );
  }

}
