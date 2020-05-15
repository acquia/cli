<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\AcquiaCliApplication;
use AcquiaCloudApi\Connector\Client;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophet;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

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
   * This method is called before each test.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function setUp(): void {
    $this->consoleOutput = new ConsoleOutput();
    $this->fs = new Filesystem();
    $this->prophet = new Prophet();

    $input = new ArrayInput([]);
    $output = new BufferedOutput();
    $logger = new ConsoleLogger($output);
    $this->fixtureDir = realpath(__DIR__ . '/../../fixtures');
    $this->projectFixtureDir = $this->fixtureDir . '/project';
    $repo_root = $this->projectFixtureDir;
    $this->application = new AcquiaCliApplication($logger, $input, $output, $repo_root, 'UNKNOWN', $this->fixtureDir . '/.acquia');
    $this->fs->remove($this->application->getCloudConfigFilepath());
    $this->fs->remove($this->application->getAcliConfigFilepath());
    $this->createMockConfigFile();

    parent::setUp();
  }

  protected function tearDown(): void {
    parent::tearDown();
    $filepath = $this->application->getCloudConfigFilepath();
    $this->fs->remove($filepath);
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
    $temp_file_name = $this->fs->tempnam(sys_get_temp_dir(), 'acli') . '.pub';
    $this->fs->dumpFile($temp_file_name, $contents);
    return $temp_file_name;
  }

  /**
   * @return \Prophecy\Prophecy\ObjectProphecy|Client $cloud_client
   */
  protected function getMockClient() {
    return $this->prophet->prophesize(Client::class);
  }

  protected function createMockConfigFile(): void {
    $contents = json_encode(['key' => 'testkey', 'secret' => 'test']);
    $filepath = $this->application->getCloudConfigFilepath();
    $this->fs->dumpFile($filepath, $contents);
  }

  /**
   * @param $cloud_client
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function mockApplicationsRequest($cloud_client) {
    // Request for applications.
    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $cloud_client->request('get', '/applications')
      ->willReturn($applications_response->{'_embedded'}->items)
      ->shouldBeCalled();
    return $applications_response;
  }

  /**
   * @param $cloud_client
   * @param object $applications_response
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function mockEnvironmentsRequest(
    $cloud_client,
    $applications_response
  ) {
    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', '200');
    $cloud_client->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$response])
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @param $cloud_client
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockIdeListRequest($cloud_client) {
    $response = $this->getMockResponseFromSpec('/api/applications/{applicationUuid}/ides',
      'get', '200');
    $cloud_client->request('get',
      '/applications/a47ac10b-58cc-4372-a567-0e02b2c3d470/ides')
      ->willReturn($response->{'_embedded'}->items)
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @param $cloud_client
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockListSshKeysRequest($cloud_client) {
    $response = $this->getMockResponseFromSpec('/account/ssh-keys', 'get',
      '200');
    $cloud_client->request('get', '/account/ssh-keys')
      ->willReturn($response->{'_embedded'}->items)
      ->shouldBeCalled();
    return $response;
  }

  /**
   * @param $cloud_client
   * @param array $mock_request_args
   */
  protected function mockUploadSshKey($cloud_client): void {
    $response = $this->prophet->prophesize(ResponseInterface::class);
    $response->getStatusCode()->willReturn(202);
    $cloud_client->makeRequest('post', '/account/ssh-keys', Argument::type('array'))
      ->willReturn($response->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param $mock_request_args
   * @param $cloud_client
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockListSshKeyRequestWithUploadedKey(
    $mock_request_args,
    $cloud_client
  ): void {
    $mock_body = $this->getMockResponseFromSpec('/account/ssh-keys', 'get',
      '200');
    $mock_body->_embedded->items[3] = (object) $mock_request_args;
    $cloud_client->request('get', '/account/ssh-keys')
      ->willReturn($mock_body->{'_embedded'}->items)
      ->shouldBeCalled();
  }

}
