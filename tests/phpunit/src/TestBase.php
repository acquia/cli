<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Application;
use Acquia\Cli\Command\ClearCacheCommand;
use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\DataStore\YamlStore;
use Acquia\Cli\Helpers\ClientService;
use Acquia\Cli\Helpers\CloudCredentials;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Exception\ApiErrorException;
use AcquiaCloudApi\Response\IdeResponse;
use AcquiaLogstream\LogstreamManager;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Webmozart\KeyValueStore\JsonFileStore;
use Webmozart\PathUtil\Path;

/**
 * Class CommandTestBase.
 * @property \Acquia\Cli\Command\CommandBase $command
 */
abstract class TestBase extends TestCase {

  /**
   * @var \Symfony\Component\Console\Output\ConsoleOutput
   */
  protected $consoleOutput;

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fs;

  /**
   * @var \Prophecy\Prophet*/
  protected $prophet;

  /**
   * @var \Symfony\Component\Console\Command\Command
   */
  protected $projectFixtureDir;

  /**
   * @var string
   */
  protected $fixtureDir;

  /**
   * @var Application
   */
  protected $application;
  /**
   * @var \Symfony\Component\Console\Input\ArrayInput
   */
  protected $input;

  protected $output;

  /**
   * @var \Prophecy\Prophecy\ObjectProphecy|\AcquiaCloudApi\Connector\Client
   */
  protected $clientProphecy;

  /** @var \Prophecy\Prophecy\ObjectProphecy|LogstreamManager */
  protected $logStreamManagerProphecy;

  /** @var array */
  protected $acliConfig = [];

  /** @var array */
  protected $cloudConfig = [];

  /**
   * @var string
   */
  protected $key = '17feaf34-5d04-402b-9a67-15d5161d24e1';

  /**
   * @var string
   */
  protected $secret = 'X1u\/PIQXtYaoeui.4RJSJpGZjwmWYmfl5AUQkAebYE=';

  /**
   * @var string
   */
  protected $dataDir;

  /**
   * @var string
   */
  protected $cloudConfigFilepath;

  /**
   * @var string
   */
  protected $acliConfigFilepath;

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  protected $datastoreAcli;

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  protected $datastoreCloud;

  /**
   * @var \Acquia\Cli\Helpers\CloudCredentials
   */
  protected $cloudCredentials;

  /**
   * @var \Acquia\Cli\Helpers\LocalMachineHelper
   */
  protected $localMachineHelper;

  /**
   * @var \Acquia\Cli\Helpers\TelemetryHelper
   */
  protected $telemetryHelper;

  /**
   * @var string
   */
  protected $acliConfigFilename;

  /**
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $clientServiceProphecy;

  /**
   * @var \Acquia\Cli\Helpers\SshHelper
   */
  protected $sshHelper;

  /**
   * @var string
   */
  protected $sshDir;

  /**
   * @var string|\Symfony\Component\Console\Command\Command
   */
  protected $acliRepoRoot;

  /**
   * @var \Symfony\Component\Console\Logger\ConsoleLogger
   */
  protected $logger;

  /**
   * This method is called before each test.
   *
   * @param null $output
   */
  protected function setUp($output = NULL): void {
    if (!$output) {
      $output = new BufferedOutput();
    }
    $input = new ArrayInput([]);

    $this->application = new Application();
    $this->fs = new Filesystem();
    $this->prophet = new Prophet();
    $this->consoleOutput = new ConsoleOutput();
    $this->fixtureDir = realpath(__DIR__ . '/../../fixtures');
    $this->projectFixtureDir = $this->fixtureDir . '/project';
    $this->acliRepoRoot = $this->projectFixtureDir;
    $this->dataDir = $this->fixtureDir . '/.acquia';
    $this->sshDir = sys_get_temp_dir();
    $this->acliConfigFilename = '.acquia-cli.yml';
    $this->cloudConfigFilepath = $this->dataDir . '/cloud_api.conf';
    $this->acliConfigFilepath = $this->projectFixtureDir . '/' . $this->acliConfigFilename;
    $this->datastoreAcli = new YamlStore($this->acliConfigFilepath);
    $this->datastoreCloud = new JsonFileStore($this->cloudConfigFilepath, 1);
    $this->cloudCredentials = new CloudCredentials($this->datastoreCloud);
    $this->clientProphecy = $this->prophet->prophesize(Client::class);
    $this->clientProphecy->addOption('headers', ['User-Agent' => 'acli/UNKNOWN', 'Accept' => 'application/json']);
    $this->clientServiceProphecy = $this->prophet->prophesize(ClientService::class);
    $this->clientServiceProphecy->getClient()->willReturn($this->clientProphecy->reveal());
    $this->logStreamManagerProphecy = $this->prophet->prophesize(LogstreamManager::class);

    $this->setIo($input, $output);

    $this->removeMockConfigFiles();
    $this->createMockConfigFiles();
    ClearCacheCommand::clearCaches();

    parent::setUp();
  }

  protected function tearDown(): void {
    parent::tearDown();
    $this->removeMockConfigFiles();
  }

  protected function setIo($input, $output) {
    $this->input = $input;
    $this->output = $output;
    $this->logger = new ConsoleLogger($output);
    $this->localMachineHelper = new LocalMachineHelper($input, $output);
    $this->localMachineHelper->setLogger($this->logger);
    $this->telemetryHelper = new TelemetryHelper($input, $output, $this->clientServiceProphecy->reveal(), $this->datastoreAcli, $this->datastoreCloud);
    $this->sshHelper = new SshHelper($output, $this->localMachineHelper);
    $this->sshHelper->setLogger($this->logger);
  }

  /**
   * @param $path
   * @param $method
   *
   * @return mixed
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getResourceFromSpec($path, $method) {
    $acquia_cloud_spec = $this->getCloudApiSpec();
    return $acquia_cloud_spec['paths'][$path][$method];
  }

  /**
   * @param $path
   * @param $method
   * @param $http_code
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   *
   * @see CXAPI-7208
   */
  public function getMockResponseFromSpec($path, $method, $http_code) {
    $endpoint = $this->getResourceFromSpec($path, $method);
    $response = $endpoint['responses'][$http_code];

    if (array_key_exists('application/json', $response['content'])) {
      $content = $response['content']['application/json'];
    }
    else {
      $content = $response['content']['application/x-www-form-urlencoded'];
    }

    if (array_key_exists('example', $content)) {
      $response_body = json_encode($content['example']);
    }
    elseif (array_key_exists('examples', $content)) {
      $response_body = json_encode($content['examples']);
    }
    elseif (array_key_exists('example', $response['content'])) {
      $response_body = json_encode($response['content']['example']);
    }
    else {
      return (object) [];
    }

    return json_decode($response_body);
  }

  /**
   * Build and return a command with common dependencies.
   *
   * All commands inherit from a common base and use the same constructor with a
   * bunch of dependencies injected. It would be tedious for every command test
   * to inject every dependency as part of createCommand(). They can use this
   * instead.
   *
   * @param string $commandName
   *
   * @return \Symfony\Component\Console\Command\Command
   */
  protected function injectCommand(string $commandName): Command {
    return new $commandName(
      $this->cloudConfigFilepath,
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->acliConfigFilename,
      $this->acliRepoRoot,
      $this->clientServiceProphecy->reveal(),
      $this->logStreamManagerProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir
    );
  }

  /**
   * @param $path
   *
   * @param string $method
   *
   * @return mixed
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function getMockRequestBodyFromSpec($path, $method = 'post') {
    $endpoint = $this->getResourceFromSpec($path, $method);
    return $endpoint['requestBody']['content']['application/json']['example'];
  }

  /**
   * @return mixed
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getCloudApiSpec() {
    // We cache the yaml file because it's 20k+ lines and takes FOREVER
    // to parse when xDebug is enabled.
    $acquia_cloud_spec_file = __DIR__ . '/../../../assets/acquia-spec.yaml';
    $acquia_cloud_spec_file_checksum = md5_file($acquia_cloud_spec_file);

    $cache = new PhpArrayAdapter(__DIR__ . '/../../../cache/ApiSpec.cache', new FilesystemAdapter());
    $is_command_cache_valid = $this->isApiSpecCacheValid($cache, $acquia_cloud_spec_file_checksum);
    $api_spec_cache_item = $cache->getItem('api_spec.yaml');
    if ($is_command_cache_valid && $api_spec_cache_item->isHit()) {
      return $api_spec_cache_item->get();
    }

    $api_spec = Yaml::parseFile($acquia_cloud_spec_file);
    $this->saveApiSpecCacheItems($cache, $acquia_cloud_spec_file_checksum, $api_spec_cache_item, $api_spec);

    return $api_spec;
  }

  /**
   * @param \Symfony\Component\Cache\Adapter\PhpArrayAdapter $cache
   *
   * @param string $acquia_cloud_spec_file_checksum
   *
   * @return bool
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function isApiSpecCacheValid(PhpArrayAdapter $cache, $acquia_cloud_spec_file_checksum): bool {
    $api_spec_checksum_item = $cache->getItem('api_spec.checksum');
    // If there's an invalid entry OR there's no entry, return false.
    return !(!$api_spec_checksum_item->isHit() || ($api_spec_checksum_item->isHit()
        && $api_spec_checksum_item->get() !== $acquia_cloud_spec_file_checksum));
  }

  /**
   * @param \Symfony\Component\Cache\Adapter\PhpArrayAdapter $cache
   * @param string $acquia_cloud_spec_file_checksum
   * @param \Symfony\Component\Cache\CacheItem $api_spec_cache_item
   * @param $api_spec
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function saveApiSpecCacheItems(
    PhpArrayAdapter $cache,
    string $acquia_cloud_spec_file_checksum,
    CacheItem $api_spec_cache_item,
    $api_spec
  ): void {
    $api_spec_checksum_item = $cache->getItem('api_spec.checksum');
    $api_spec_checksum_item->set($acquia_cloud_spec_file_checksum);
    $cache->save($api_spec_checksum_item);
    $api_spec_cache_item->set($api_spec);
    $cache->save($api_spec_cache_item);
  }

  /**
   * @param $contents
   *
   * @return string
   */
  protected function createLocalSshKey($contents): string {
    $finder = new Finder();
    $finder->files()->in(sys_get_temp_dir())->name('*.pub')->ignoreUnreadableDirs();
    $this->fs->remove($finder->files());
    $private_key_filepath = $this->fs->tempnam(sys_get_temp_dir(), 'acli');
    $this->fs->touch($private_key_filepath);
    $public_key_filepath = $private_key_filepath . '.pub';
    $this->fs->dumpFile($public_key_filepath, $contents);

    return $public_key_filepath;
  }

  protected function createMockConfigFiles(): void {
    $this->createMockCloudConfigFile();

    $default_values = [];
    $acli_config = array_merge($default_values, $this->acliConfig);
    $contents = json_encode($acli_config);
    $filepath = $this->acliConfigFilepath;
    $this->fs->dumpFile($filepath, $contents);
  }

  protected function createMockCloudConfigFile($default_values = []) {
    if (!$default_values) {
      $default_values = [
        'acli_key' => $this->key,
        'keys' => [
          (string) ($this->key) => [
            'uuid' => $this->key,
            'label' => 'Test Key',
            'secret' => $this->secret,
          ],
        ],
        DataStoreContract::SEND_TELEMETRY => FALSE,
      ];
    }
    $cloud_config = array_merge($default_values, $this->cloudConfig);
    $contents = json_encode($cloud_config);
    $filepath = $this->cloudConfigFilepath;
    $this->fs->dumpFile($filepath, $contents);
  }

  protected function createMockAcliConfigFile($cloud_app_uuid): void {
    $this->datastoreAcli->set('cloud_app_uuid', $cloud_app_uuid);
  }

  /**
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function mockApplicationsRequest() {
    // Request for applications.
    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $this->clientProphecy->request('get', '/applications')
      ->willReturn($applications_response->{'_embedded'}->items)
      ->shouldBeCalled();
    return $applications_response;
  }

  public function mockUnauthorizedRequest(): void {
    $response = [
      'error' => 'invalid_client',
      'error_description' => 'Client credentials were not found in the headers or body',
    ];
    $this->clientProphecy->request('get', Argument::type('string'))
      ->willThrow(new IdentityProviderException($response['error'], 0, $response));
  }

  public function mockApiError(): void {
    $response = (object) [
      'message' => 'some error',
      'error' => 'some error',
    ];
    $this->clientProphecy->request('get', Argument::type('string'))
      ->willThrow(new ApiErrorException($response, $response->message));
  }

  public function mockNoAvailableIdes(): void {
    $response = (object) [
      'message' => "There are no available Cloud IDEs for this application.\n",
      'error' => "There are no available Cloud IDEs for this application.\n",
    ];
    $this->clientProphecy->request('get', Argument::type('string'))
      ->willThrow(new ApiErrorException($response, $response->message));
  }

  /**
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockApplicationRequest() {
    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $application_response = $applications_response->{'_embedded'}->items[0];
    $this->clientProphecy->request('get',
      '/applications/' . $applications_response->{'_embedded'}->items[0]->uuid)
      ->willReturn($application_response)
      ->shouldBeCalled();

    return $application_response;
  }

  /**
   * @param object $applications_response
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function mockEnvironmentsRequest(
    $applications_response
  ) {
    $response = $this->getMockEnvironmentResponse();
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$response])
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @param string $method
   *
   * @param string $http_code
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getMockEnvironmentResponse($method = 'get', $http_code = '200') {
    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209. It should be
    // applications/{applicationUuid}/environments.
    $response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      $method, $http_code);
    $response->platform = 'cloud';

    return $response;
  }

  /**
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockIdeListRequest() {
    $response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/ides',
      'get', '200');
    $this->clientProphecy->request('get',
      '/applications/a47ac10b-58cc-4372-a567-0e02b2c3d470/ides')
      ->willReturn($response->{'_embedded'}->items)
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @param string $ide_uuid
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockGetIdeRequest(string $ide_uuid) {
    $ide_response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/ides/' . $ide_uuid)->willReturn($ide_response)->shouldBeCalled();
    return $ide_response;
  }

  /**
   * @param string $ide_uuid
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockIdeDeleteRequest(string $ide_uuid) {
    $ide_delete_response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'delete', '202');
    $this->clientProphecy->request('delete', '/ides/' . $ide_uuid)
      ->willReturn($ide_delete_response->{'De-provisioning IDE'}->value)
      ->shouldBeCalled();
    return $ide_delete_response;
  }

  /**
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockLogListRequest() {
    $response = $this->getMockResponseFromSpec('/environments/{environmentId}/logs',
      'get', '200');
    $this->clientProphecy->request('get',
      '/environments/24-a47ac10b-58cc-4372-a567-0e02b2c3d470/logs')
      ->willReturn($response->{'_embedded'}->items)
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockLogStreamRequest() {
    $response = $this->getMockResponseFromSpec('/environments/{environmentId}/logstream',
      'get', '200');
    $this->clientProphecy->request('get',
      '/environments/24-a47ac10b-58cc-4372-a567-0e02b2c3d470/logstream')
      ->willReturn($response)
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockListSshKeysRequest() {
    $response = $this->getMockResponseFromSpec('/account/ssh-keys', 'get',
      '200');
    $this->clientProphecy->request('get', '/account/ssh-keys')
      ->willReturn($response->{'_embedded'}->items)
      ->shouldBeCalled();
    return $response;
  }

  /**
   * @param \AcquiaCloudApi\Response\IdeResponse $ide
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockListSshKeysRequestWithIdeKey(IdeResponse $ide) {
    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $mock_body->{'_embedded'}->items[0]->label = SshKeyCommandBase::getIdeSshKeyLabel($ide);
    $this->clientProphecy->request('get', '/account/ssh-keys')
      ->willReturn($mock_body->{'_embedded'}->items)
      ->shouldBeCalled();
    return $mock_body;
  }

  /**
   */
  protected function mockUploadSshKey(): void {
    /** @var \Prophecy\Prophecy\ObjectProphecy|ResponseInterface $response */
    $response = $this->prophet->prophesize(ResponseInterface::class);
    $response->getStatusCode()->willReturn(202);
    $this->clientProphecy->makeRequest('post', '/account/ssh-keys', Argument::type('array'))
      ->willReturn($response->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \AcquiaCloudApi\Response\IdeResponse $ide
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockGetIdeSshKeyRequest(IdeResponse $ide): void {
    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $mock_body->{'_embedded'}->items[0]->label = SshKeyCommandBase::getIdeSshKeyLabel($ide);
    $this->clientProphecy->request('get', '/account/ssh-keys/' . $mock_body->{'_embedded'}->items[0]->uuid)
      ->willReturn($mock_body->{'_embedded'}->items[0])
      ->shouldBeCalled();
  }

  /**
   * @param string $key_uuid
   */
  protected function mockDeleteSshKeyRequest($key_uuid): void {
    // Request ssh key deletion.
    $ssh_key_delete_response = $this->prophet->prophesize(ResponseInterface::class);
    $ssh_key_delete_response->getStatusCode()->willReturn(202);
    $this->clientProphecy->makeRequest('delete',
      '/account/ssh-keys/' . $key_uuid)
      ->willReturn($ssh_key_delete_response->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param $mock_request_args
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockListSshKeyRequestWithUploadedKey(
    $mock_request_args
  ): void {
    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get',
      '200');
    $mock_body->_embedded->items[3] = (object) $mock_request_args;
    $this->clientProphecy->request('get', '/account/ssh-keys')
      ->willReturn($mock_body->{'_embedded'}->items)
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockRestartPhp(ObjectProphecy $local_machine_helper): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $local_machine_helper->execute([
      'supervisorctl',
      'restart',
      'php-fpm',
    ], NULL, NULL, FALSE)->willReturn($process->reveal())->shouldBeCalled();
    return $process;
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   */
  protected function mockRestartBash(ObjectProphecy $local_machine_helper): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $local_machine_helper->executeFromCmd('exec bash -l', NULL, NULL, TRUE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   */
  protected function mockGetFilesystem(ObjectProphecy $local_machine_helper): void {
    $local_machine_helper->getFilesystem()->willReturn($this->fs)->shouldBeCalled();
  }

  protected function removeMockConfigFiles(): void {
    $this->removeMockCloudConfigFile();
    $this->removeMockAcliConfigFile();
  }

  protected function removeMockCloudConfigFile(): void {
    $this->fs->remove($this->cloudConfigFilepath);
  }

  protected function removeMockAcliConfigFile(): void {
    $this->fs->remove($this->acliConfigFilepath);
  }

  /**
   * @param array $releases
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  public function mockGuzzleClientForUpdate($releases): ObjectProphecy {
    $stream = $this->prophet->prophesize(StreamInterface::class);
    $stream->getContents()->willReturn(json_encode($releases));
    $response = $this->prophet->prophesize(Response::class);
    $response->getBody()->willReturn($stream->reveal());
    $guzzle_client = $this->prophet->prophesize(\GuzzleHttp\Client::class);
    $guzzle_client->request('GET', Argument::containingString('https://api.github.com/repos'), Argument::type('array'))
      ->willReturn($response->reveal());

    $stream = $this->prophet->prophesize(StreamInterface::class);
    $phar_contents = file_get_contents(Path::join($this->fixtureDir, 'test.phar'));
    $stream->getContents()->willReturn($phar_contents);
    $response = $this->prophet->prophesize(Response::class);
    $response->getBody()->willReturn($stream->reveal());
    $guzzle_client->request('GET', 'https://github.com/acquia/cli/releases/download/v1.0.0-beta3/acli.phar',
      Argument::type('array'))->willReturn($response->reveal());

    return $guzzle_client;
  }

}
