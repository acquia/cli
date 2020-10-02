<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\Helpers\ClientService;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Helpers\UpdateHelper;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Response\IdeResponse;
use AcquiaLogstream\LogstreamManager;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Application;
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
use Zumba\Amplitude\Amplitude;

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
   * @var \Prophecy\Prophecy\ObjectProphecy|Amplitude
   */
  protected $amplitudeProphecy;

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
  protected $acliDatastore;

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  protected $cloudDatastore;

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
   * @var \Acquia\Cli\Helpers\UpdateHelper
   */
  protected $updateHelper;

  /**
   * This method is called before each test.
   *
   * @param null $output
   */
  protected function setUp($output = NULL): void {
    if (!$output) {
      $output = new BufferedOutput();
    }

    $this->application = new Application();
    $this->fs = new Filesystem();
    $this->prophet = new Prophet();
    $this->consoleOutput = new ConsoleOutput();
    $this->input = new ArrayInput([]);
    $this->output = $output;
    $logger = new ConsoleLogger($output);
    $this->fixtureDir = realpath(__DIR__ . '/../../fixtures');
    $this->projectFixtureDir = $this->fixtureDir . '/project';
    $this->acliRepoRoot = $this->projectFixtureDir;
    $this->dataDir = $this->fixtureDir . '/.acquia';
    $this->sshDir = sys_get_temp_dir();
    $this->acliConfigFilename = 'acquia-cli.json';
    $this->cloudConfigFilepath = $this->dataDir . '/cloud_api.conf';
    $this->acliConfigFilepath = $this->dataDir . '/' . $this->acliConfigFilename;
    $this->acliDatastore = new JsonFileStore($this->acliConfigFilepath);
    $this->cloudDatastore = new JsonFileStore($this->cloudConfigFilepath, 1);
    $this->amplitudeProphecy = $this->prophet->prophesize(Amplitude::class);
    $this->clientProphecy = $this->prophet->prophesize(Client::class);
    $this->clientProphecy->addOption('headers', ['User-Agent' => 'acli/UNKNOWN', 'Accept' => 'application/json']);
    $this->localMachineHelper = new LocalMachineHelper($this->input, $output, $logger);
    $this->updateHelper = new UpdateHelper();
    $guzzle_client = $this->mockGuzzleClientForUpdate($this->mockGitHubReleasesResponse());
    $this->updateHelper->setClient($guzzle_client->reveal());
    $this->clientServiceProphecy = $this->prophet->prophesize(ClientService::class);
    $this->clientServiceProphecy->getClient()->willReturn($this->clientProphecy->reveal());
    $this->telemetryHelper = new TelemetryHelper($this->input, $output, $this->clientServiceProphecy->reveal(), $this->acliDatastore, $this->cloudDatastore);
    $this->logStreamManagerProphecy = $this->prophet->prophesize(LogstreamManager::class);
    $this->sshHelper = new SshHelper($output, $this->localMachineHelper);

    $this->removeMockConfigFiles();
    $this->createMockConfigFile();

    parent::setUp();
  }

  protected function tearDown(): void {
    parent::tearDown();
    $this->removeMockConfigFiles();
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
      $this->updateHelper,
      $this->cloudDatastore,
      $this->acliDatastore,
      $this->telemetryHelper,
      $this->amplitudeProphecy->reveal(),
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

  protected function createMockConfigFile(): void {
    // @todo Read from config object.
    $default_values = ['key' => 'testkey', 'secret' => 'test'];
    $cloud_config = array_merge($default_values, $this->cloudConfig);
    $contents = json_encode($cloud_config);
    $filepath = $this->cloudConfigFilepath;
    $this->fs->dumpFile($filepath, $contents);

    $default_values = [DataStoreContract::SEND_TELEMETRY => FALSE];
    $acli_config = array_merge($default_values, $this->acliConfig);
    $contents = json_encode($acli_config);
    $filepath = $this->acliConfigFilepath;
    $this->fs->dumpFile($filepath, $contents);
  }

  protected function createMockAcliConfigFile($cloud_app_uuid): void {
    $this->acliDatastore->set($this->acliConfigFilename, [
      'localProjects' => [
        0 => [
          'directory' => $this->projectFixtureDir,
          'cloud_application_uuid' => $cloud_app_uuid,
        ],
      ],
    ]);
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
    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', '200');
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$response])
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockIdeListRequest() {
    $response = $this->getMockResponseFromSpec('/api/applications/{applicationUuid}/ides',
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

  /**
   * @return array
   */
  public function mockGitHubReleasesResponse(): array {
    $response = [
      0 =>
        [
          'url' => 'https://api.github.com/repos/acquia/cli/releases/27415350',
          'assets_url' => 'https://api.github.com/repos/acquia/cli/releases/27415350/assets',
          'upload_url' => 'https://uploads.github.com/repos/acquia/cli/releases/27415350/assets{?name,label}',
          'html_url' => 'https://github.com/acquia/cli/releases/tag/v1.0.0-beta4',
          'id' => 27415350,
          'node_id' => 'MDc6UmVsZWFzZTI3NDE1MzUw',
          'tag_name' => 'v1.0.0-beta4',
          'target_commitish' => 'master',
          'name' => 'v1.0.0-beta4',
          'draft' => FALSE,
          'author' =>
            [
              'login' => 'grasmash',
              'id' => 539205,
              'node_id' => 'MDQ6VXNlcjUzOTIwNQ==',
              'avatar_url' => 'https://avatars0.githubusercontent.com/u/539205?v=4',
              'gravatar_id' => '',
              'url' => 'https://api.github.com/users/grasmash',
              'html_url' => 'https://github.com/grasmash',
              'followers_url' => 'https://api.github.com/users/grasmash/followers',
              'following_url' => 'https://api.github.com/users/grasmash/following{/other_user}',
              'gists_url' => 'https://api.github.com/users/grasmash/gists{/gist_id}',
              'starred_url' => 'https://api.github.com/users/grasmash/starred{/owner}{/repo}',
              'subscriptions_url' => 'https://api.github.com/users/grasmash/subscriptions',
              'organizations_url' => 'https://api.github.com/users/grasmash/orgs',
              'repos_url' => 'https://api.github.com/users/grasmash/repos',
              'events_url' => 'https://api.github.com/users/grasmash/events{/privacy}',
              'received_events_url' => 'https://api.github.com/users/grasmash/received_events',
              'type' => 'User',
              'site_admin' => FALSE,
            ],
          'prerelease' => TRUE,
          'created_at' => '2020-06-10T01:40:15Z',
          'published_at' => '2020-06-10T14:48:22Z',
          'assets' => [],
          'tarball_url' => 'https://api.github.com/repos/acquia/cli/tarball/v1.0.0-beta4',
          'zipball_url' => 'https://api.github.com/repos/acquia/cli/zipball/v1.0.0-beta4',
          'body' => '- Prevent updating to releases with missing phars. (#136)
- Correcting usage example for api:* option with array value. (#138)
- Correctly set Phar path for self update. (#137)
',
        ],
      1 =>
        [
          'url' => 'https://api.github.com/repos/acquia/cli/releases/27387040',
          'assets_url' => 'https://api.github.com/repos/acquia/cli/releases/27387040/assets',
          'upload_url' => 'https://uploads.github.com/repos/acquia/cli/releases/27387040/assets{?name,label}',
          'html_url' => 'https://github.com/acquia/cli/releases/tag/v1.0.0-beta3',
          'id' => 27387040,
          'node_id' => 'MDc6UmVsZWFzZTI3Mzg3MDQw',
          'tag_name' => 'v1.0.0-beta3',
          'target_commitish' => 'master',
          'name' => 'v1.0.0-beta3',
          'draft' => FALSE,
          'author' =>
            [
              'login' => 'grasmash',
              'id' => 539205,
              'node_id' => 'MDQ6VXNlcjUzOTIwNQ==',
              'avatar_url' => 'https://avatars0.githubusercontent.com/u/539205?v=4',
              'gravatar_id' => '',
              'url' => 'https://api.github.com/users/grasmash',
              'html_url' => 'https://github.com/grasmash',
              'followers_url' => 'https://api.github.com/users/grasmash/followers',
              'following_url' => 'https://api.github.com/users/grasmash/following{/other_user}',
              'gists_url' => 'https://api.github.com/users/grasmash/gists{/gist_id}',
              'starred_url' => 'https://api.github.com/users/grasmash/starred{/owner}{/repo}',
              'subscriptions_url' => 'https://api.github.com/users/grasmash/subscriptions',
              'organizations_url' => 'https://api.github.com/users/grasmash/orgs',
              'repos_url' => 'https://api.github.com/users/grasmash/repos',
              'events_url' => 'https://api.github.com/users/grasmash/events{/privacy}',
              'received_events_url' => 'https://api.github.com/users/grasmash/received_events',
              'type' => 'User',
              'site_admin' => FALSE,
            ],
          'prerelease' => TRUE,
          'created_at' => '2020-06-09T20:48:56Z',
          'published_at' => '2020-06-09T20:54:10Z',
          'assets' =>
            [
              0 =>
                [
                  'url' => 'https://api.github.com/repos/acquia/cli/releases/assets/21591990',
                  'id' => 21591990,
                  'node_id' => 'MDEyOlJlbGVhc2VBc3NldDIxNTkxOTkw',
                  'name' => 'acli.phar',
                  'label' => NULL,
                  'uploader' =>
                    [
                      'login' => 'grasmash',
                      'id' => 539205,
                      'node_id' => 'MDQ6VXNlcjUzOTIwNQ==',
                      'avatar_url' => 'https://avatars0.githubusercontent.com/u/539205?v=4',
                      'gravatar_id' => '',
                      'url' => 'https://api.github.com/users/grasmash',
                      'html_url' => 'https://github.com/grasmash',
                      'followers_url' => 'https://api.github.com/users/grasmash/followers',
                      'following_url' => 'https://api.github.com/users/grasmash/following{/other_user}',
                      'gists_url' => 'https://api.github.com/users/grasmash/gists{/gist_id}',
                      'starred_url' => 'https://api.github.com/users/grasmash/starred{/owner}{/repo}',
                      'subscriptions_url' => 'https://api.github.com/users/grasmash/subscriptions',
                      'organizations_url' => 'https://api.github.com/users/grasmash/orgs',
                      'repos_url' => 'https://api.github.com/users/grasmash/repos',
                      'events_url' => 'https://api.github.com/users/grasmash/events{/privacy}',
                      'received_events_url' => 'https://api.github.com/users/grasmash/received_events',
                      'type' => 'User',
                      'site_admin' => FALSE,
                    ],
                  'content_type' => 'application/octet-stream',
                  'state' => 'uploaded',
                  'size' => 9158519,
                  'download_count' => 27,
                  'created_at' => '2020-06-09T21:13:34Z',
                  'updated_at' => '2020-06-09T21:13:37Z',
                  'browser_download_url' => 'https://github.com/acquia/cli/releases/download/v1.0.0-beta3/acli.phar',
                ],
            ],
          'tarball_url' => 'https://api.github.com/repos/acquia/cli/tarball/v1.0.0-beta3',
          'zipball_url' => 'https://api.github.com/repos/acquia/cli/zipball/v1.0.0-beta3',
          'body' => '- Add git clone scenario to refresh command. (#107)
- Fixes #121: Ship required .sh file with phar. (#122) …
- Removing command cache. (#125) …
- Reduce phar size with compactors (#127)
- Fixes #120: Broken parameters for api:* commands. (#126)
- Fixes #123: Infer applicationUuid argument for api:* commands. (#128)
- Check blt.yml for Cloud app uuid. (#130)
- Fixes #132: Allowing multiple arguments for remote:drush command. (#133)',
        ],
      2 =>
        [
          'url' => 'https://api.github.com/repos/acquia/cli/releases/27281238',
          'assets_url' => 'https://api.github.com/repos/acquia/cli/releases/27281238/assets',
          'upload_url' => 'https://uploads.github.com/repos/acquia/cli/releases/27281238/assets{?name,label}',
          'html_url' => 'https://github.com/acquia/cli/releases/tag/v1.0.0-beta2',
          'id' => 27281238,
          'node_id' => 'MDc6UmVsZWFzZTI3MjgxMjM4',
          'tag_name' => 'v1.0.0-beta2',
          'target_commitish' => '244668f023ec5b95c3ed403e5b43b397faaa2d12',
          'name' => 'v1.0.0-beta2',
          'draft' => FALSE,
          'author' =>
            [
              'login' => 'danepowell',
              'id' => 1984514,
              'node_id' => 'MDQ6VXNlcjE5ODQ1MTQ=',
              'avatar_url' => 'https://avatars1.githubusercontent.com/u/1984514?v=4',
              'gravatar_id' => '',
              'url' => 'https://api.github.com/users/danepowell',
              'html_url' => 'https://github.com/danepowell',
              'followers_url' => 'https://api.github.com/users/danepowell/followers',
              'following_url' => 'https://api.github.com/users/danepowell/following{/other_user}',
              'gists_url' => 'https://api.github.com/users/danepowell/gists{/gist_id}',
              'starred_url' => 'https://api.github.com/users/danepowell/starred{/owner}{/repo}',
              'subscriptions_url' => 'https://api.github.com/users/danepowell/subscriptions',
              'organizations_url' => 'https://api.github.com/users/danepowell/orgs',
              'repos_url' => 'https://api.github.com/users/danepowell/repos',
              'events_url' => 'https://api.github.com/users/danepowell/events{/privacy}',
              'received_events_url' => 'https://api.github.com/users/danepowell/received_events',
              'type' => 'User',
              'site_admin' => FALSE,
            ],
          'prerelease' => TRUE,
          'created_at' => '2020-06-05T22:54:32Z',
          'published_at' => '2020-06-05T22:57:52Z',
          'assets' =>
            [
              0 =>
                [
                  'url' => 'https://api.github.com/repos/acquia/cli/releases/assets/21460998',
                  'id' => 21460998,
                  'node_id' => 'MDEyOlJlbGVhc2VBc3NldDIxNDYwOTk4',
                  'name' => 'acli.phar',
                  'label' => '',
                  'uploader' =>
                    [
                      'login' => 'acquia-cli-deploy',
                      'id' => 66086891,
                      'node_id' => 'MDQ6VXNlcjY2MDg2ODkx',
                      'avatar_url' => 'https://avatars3.githubusercontent.com/u/66086891?v=4',
                      'gravatar_id' => '',
                      'url' => 'https://api.github.com/users/acquia-cli-deploy',
                      'html_url' => 'https://github.com/acquia-cli-deploy',
                      'followers_url' => 'https://api.github.com/users/acquia-cli-deploy/followers',
                      'following_url' => 'https://api.github.com/users/acquia-cli-deploy/following{/other_user}',
                      'gists_url' => 'https://api.github.com/users/acquia-cli-deploy/gists{/gist_id}',
                      'starred_url' => 'https://api.github.com/users/acquia-cli-deploy/starred{/owner}{/repo}',
                      'subscriptions_url' => 'https://api.github.com/users/acquia-cli-deploy/subscriptions',
                      'organizations_url' => 'https://api.github.com/users/acquia-cli-deploy/orgs',
                      'repos_url' => 'https://api.github.com/users/acquia-cli-deploy/repos',
                      'events_url' => 'https://api.github.com/users/acquia-cli-deploy/events{/privacy}',
                      'received_events_url' => 'https://api.github.com/users/acquia-cli-deploy/received_events',
                      'type' => 'User',
                      'site_admin' => FALSE,
                    ],
                  'content_type' => 'application/octet-stream',
                  'state' => 'uploaded',
                  'size' => 9815202,
                  'download_count' => 76,
                  'created_at' => '2020-06-05T23:01:59Z',
                  'updated_at' => '2020-06-05T23:02:00Z',
                  'browser_download_url' => 'https://github.com/acquia/cli/releases/download/v1.0.0-beta2/acli.phar',
                ],
            ],
          'tarball_url' => 'https://api.github.com/repos/acquia/cli/tarball/v1.0.0-beta2',
          'zipball_url' => 'https://api.github.com/repos/acquia/cli/zipball/v1.0.0-beta2',
          'body' => '- Fix self-update command (#89)
- Check if machine is already authenticated for auth:login (#100)
- Fixes #96: Remove api:accounts:drush-aliases command. #97
- Allowing Cloud app ID to be passed to ide:* commands. (#102)
- Adding cloud-env-uuid to log tail command. (#105)
- Check if repository is already linked in link command. (#101)
- Fixes #110: api:environments:domains-clear-varnish not working. (#115)',
        ],
      3 =>
        [
          'url' => 'https://api.github.com/repos/acquia/cli/releases/27104247',
          'assets_url' => 'https://api.github.com/repos/acquia/cli/releases/27104247/assets',
          'upload_url' => 'https://uploads.github.com/repos/acquia/cli/releases/27104247/assets{?name,label}',
          'html_url' => 'https://github.com/acquia/cli/releases/tag/v1.0.0-beta1',
          'id' => 27104247,
          'node_id' => 'MDc6UmVsZWFzZTI3MTA0MjQ3',
          'tag_name' => 'v1.0.0-beta1',
          'target_commitish' => 'f291b8401530d8c65810ccc758ea09262778ecbd',
          'name' => 'v1.0.0-beta1',
          'draft' => FALSE,
          'author' =>
            [
              'login' => 'danepowell',
              'id' => 1984514,
              'node_id' => 'MDQ6VXNlcjE5ODQ1MTQ=',
              'avatar_url' => 'https://avatars1.githubusercontent.com/u/1984514?v=4',
              'gravatar_id' => '',
              'url' => 'https://api.github.com/users/danepowell',
              'html_url' => 'https://github.com/danepowell',
              'followers_url' => 'https://api.github.com/users/danepowell/followers',
              'following_url' => 'https://api.github.com/users/danepowell/following{/other_user}',
              'gists_url' => 'https://api.github.com/users/danepowell/gists{/gist_id}',
              'starred_url' => 'https://api.github.com/users/danepowell/starred{/owner}{/repo}',
              'subscriptions_url' => 'https://api.github.com/users/danepowell/subscriptions',
              'organizations_url' => 'https://api.github.com/users/danepowell/orgs',
              'repos_url' => 'https://api.github.com/users/danepowell/repos',
              'events_url' => 'https://api.github.com/users/danepowell/events{/privacy}',
              'received_events_url' => 'https://api.github.com/users/danepowell/received_events',
              'type' => 'User',
              'site_admin' => FALSE,
            ],
          'prerelease' => TRUE,
          'created_at' => '2020-06-01T17:09:57Z',
          'published_at' => '2020-06-01T17:14:25Z',
          'assets' =>
            [
              0 =>
                [
                  'url' => 'https://api.github.com/repos/acquia/cli/releases/assets/21258604',
                  'id' => 21258604,
                  'node_id' => 'MDEyOlJlbGVhc2VBc3NldDIxMjU4NjA0',
                  'name' => 'acli.phar',
                  'label' => '',
                  'uploader' =>
                    [
                      'login' => 'acquia-cli-deploy',
                      'id' => 66086891,
                      'node_id' => 'MDQ6VXNlcjY2MDg2ODkx',
                      'avatar_url' => 'https://avatars3.githubusercontent.com/u/66086891?v=4',
                      'gravatar_id' => '',
                      'url' => 'https://api.github.com/users/acquia-cli-deploy',
                      'html_url' => 'https://github.com/acquia-cli-deploy',
                      'followers_url' => 'https://api.github.com/users/acquia-cli-deploy/followers',
                      'following_url' => 'https://api.github.com/users/acquia-cli-deploy/following{/other_user}',
                      'gists_url' => 'https://api.github.com/users/acquia-cli-deploy/gists{/gist_id}',
                      'starred_url' => 'https://api.github.com/users/acquia-cli-deploy/starred{/owner}{/repo}',
                      'subscriptions_url' => 'https://api.github.com/users/acquia-cli-deploy/subscriptions',
                      'organizations_url' => 'https://api.github.com/users/acquia-cli-deploy/orgs',
                      'repos_url' => 'https://api.github.com/users/acquia-cli-deploy/repos',
                      'events_url' => 'https://api.github.com/users/acquia-cli-deploy/events{/privacy}',
                      'received_events_url' => 'https://api.github.com/users/acquia-cli-deploy/received_events',
                      'type' => 'User',
                      'site_admin' => FALSE,
                    ],
                  'content_type' => 'application/octet-stream',
                  'state' => 'uploaded',
                  'size' => 7010058,
                  'download_count' => 268,
                  'created_at' => '2020-06-01T17:19:28Z',
                  'updated_at' => '2020-06-01T17:19:29Z',
                  'browser_download_url' => 'https://github.com/acquia/cli/releases/download/v1.0.0-beta1/acli.phar',
                ],
            ],
          'tarball_url' => 'https://api.github.com/repos/acquia/cli/tarball/v1.0.0-beta1',
          'zipball_url' => 'https://api.github.com/repos/acquia/cli/zipball/v1.0.0-beta1',
          'body' => 'Initial release.',
        ],
    ];

    return $response;
  }

}
