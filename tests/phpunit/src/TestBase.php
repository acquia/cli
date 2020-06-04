<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\AcquiaCliApplication;
use Acquia\Cli\Helpers\ClientService;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaCloudApi\Connector\Client;
use AcquiaLogstream\LogstreamManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophet;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Webmozart\KeyValueStore\JsonFileStore;
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
   * @var AcquiaCliApplication
   */
  protected $application;
  /**
   * @var \Symfony\Component\Console\Input\ArrayInput
   */
  protected $input;

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
   * This method is called before each test.
   *
   * @param null $output
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function setUp($output = NULL): void {
    if (!$output) {
      $output = new BufferedOutput();
    }

    $this->fs = new Filesystem();
    $this->prophet = new Prophet();
    $this->consoleOutput = new ConsoleOutput();
    $this->input = new ArrayInput([]);
    $logger = new ConsoleLogger($output);
    $this->fixtureDir = realpath(__DIR__ . '/../../fixtures');
    $this->projectFixtureDir = $this->fixtureDir . '/project';
    $this->amplitudeProphecy = $this->prophet->prophesize(Amplitude::class);
    $this->clientProphecy = $this->prophet->prophesize(Client::class);

    $container = new ContainerBuilder();
    $container->setParameter('repo_root', $this->projectFixtureDir);
    $container->setParameter('data_dir', $this->fixtureDir . '/.acquia');
    AcquiaCliApplication::configureContainer($container, $this->input, $output, $logger);
    $this->configureContainer($container);

    $this->application = new AcquiaCliApplication($container, $logger, $this->input, $output, 'UNKNOWN');

    $this->removeMockConfigFiles();
    $this->createMockConfigFile();

    parent::setUp();
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   */
  protected function configureContainer(ContainerBuilder $container): void {
    // Amplitude service.
    $container->set('amplitude', $this->amplitudeProphecy->reveal());

    // Cloud API service.
    /** @var Client $client */
    $client = $this->clientProphecy->reveal();
    $serviceProph = $this->prophet->prophesize(ClientService::class);
    $serviceProph->getClient()->willReturn($client);
    $container->set('cloud_api', $serviceProph->reveal());

    // Logstream manager.
    $this->logStreamManagerProphecy = $this->prophet->prophesize(LogstreamManager::class);
    $container->set('logstream_manager', $this->logStreamManagerProphecy->reveal());
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
   * @param $path
   *
   * @return mixed
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function getMockRequestBodyFromSpec($path) {
    $endpoint = $this->getResourceFromSpec($path, 'post');
    return $endpoint['requestBody']['content']['application/json']['example'];
  }

  /**
   * @return mixed
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function getCloudApiSpec() {
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
    $filepath = $this->application->getContainer()->getParameter('cloud_config.filepath');
    $this->fs->dumpFile($filepath, $contents);

    $default_values = [DataStoreContract::SEND_TELEMETRY => FALSE];
    $acli_config = array_merge($default_values, $this->acliConfig);
    $contents = json_encode($acli_config);
    $filepath = $this->application->getContainer()->getParameter('acli_config.filepath');
    $this->fs->dumpFile($filepath, $contents);
  }

  protected function createMockAcliConfigFile($cloud_app_uuid): void {
    $this->application->getContainer()->get('acli_datastore')->set($this->application->getContainer()->getParameter('acli_config.filename'), [
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
   */
  protected function mockUploadSshKey(): void {
    $response = $this->prophet->prophesize(ResponseInterface::class);
    $response->getStatusCode()->willReturn(202);
    $this->clientProphecy->makeRequest('post', '/account/ssh-keys', Argument::type('array'))
      ->willReturn($response->reveal())
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

  protected function removeMockConfigFiles(): void {
    $this->removeMockCloudConfigFile();
    $this->removeMockAcliConfigFile();
  }

  protected function removeMockCloudConfigFile(): void {
    $this->fs->remove($this->application->getContainer()->getParameter('cloud_config.filepath'));
  }

  protected function removeMockAcliConfigFile(): void {
    $this->fs->remove($this->application->getContainer()->getParameter('acli_config.filepath'));
  }

}
