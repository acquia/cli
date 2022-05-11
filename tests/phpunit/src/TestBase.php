<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\Command\ClearCacheCommand;
use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\Config\AcquiaCliConfig;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Exception\ApiErrorException;
use AcquiaCloudApi\Response\ApplicationResponse;
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
use React\EventLoop\Factory;
use React\EventLoop\Loop;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CommandTestBase.
 * @property \Acquia\Cli\Command\CommandBase $command
 */
abstract class TestBase extends TestCase {

  protected $apiSpecFixtureFilePath = __DIR__ . '/../../../assets/acquia-spec.yaml';

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
   * @var \Acquia\Cli\DataStore\AcquiaCliDatastore
   */
  protected $datastoreAcli;

  /**
   * @var \Acquia\Cli\DataStore\CloudDataStore
   */
  protected $datastoreCloud;

  /**
   * @var \Acquia\Cli\ApiCredentialsInterface
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

  protected $passphraseFilepath;

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
    $this->setClientProphecies();
    $this->setIo($input, $output);

    $this->fixtureDir = $this->getTempDir();
    $this->fs->mirror(realpath(__DIR__ . '/../../fixtures'), $this->fixtureDir);
    $this->projectFixtureDir = $this->fixtureDir . '/project';
    $this->acliRepoRoot = $this->projectFixtureDir;
    $this->dataDir = $this->fixtureDir . '/.acquia';
    $this->sshDir = $this->getTempDir();
    $this->acliConfigFilename = '.acquia-cli.yml';
    $this->cloudConfigFilepath = $this->dataDir . '/cloud_api.conf';
    $this->acliConfigFilepath = $this->projectFixtureDir . '/' . $this->acliConfigFilename;
    $this->createMockConfigFiles();
    $this->createDataStores();
    $this->cloudCredentials = new CloudCredentials($this->datastoreCloud);
    $this->telemetryHelper = new TelemetryHelper($input, $output, $this->clientServiceProphecy->reveal(), $this->datastoreAcli, $this->datastoreCloud);
    $this->logStreamManagerProphecy = $this->prophet->prophesize(LogstreamManager::class);
    ClearCacheCommand::clearCaches();

    parent::setUp();
  }

  /**
   * Create a guaranteed-unique temporary directory.
   *
   * @throws \Exception
   */
  private function getTempDir() {
    /**
     * sys_get_temp_dir() is not thread-safe but it's okay to use here since
     * we are specifically creating a thread-safe temporary directory.
     */
    // phpcs:ignore
    $dir = sys_get_temp_dir();

    // /tmp is a symlink to /private/tmp on Mac, which causes inconsistency when
    // normalizing paths.
    if (PHP_OS_FAMILY === 'Darwin') {
      $dir = Path::join('/private', $dir);
    }

    /* If we don't have permission to create a directory, fail, otherwise we will
     * be stuck in an endless loop.
     */
    if (!is_dir($dir) || !is_writable($dir)) {
      return FALSE;
    }

    /* Attempt to create a random directory until it works. Abort if we reach
     * $maxAttempts. Something screwy could be happening with the filesystem
     * and our loop could otherwise become endless.
     */
    $attempts = 0;
    do {
      $path = sprintf('%s%s%s%s', $dir, DIRECTORY_SEPARATOR, 'tmp_', random_int(100000, mt_getrandmax()));
    } while (
      !mkdir($path, 0700) &&
      $attempts++ < 10
    );

    return $path;
  }

  protected function tearDown(): void {
    parent::tearDown();
    // $loop is statically cached by Loop::get() in some tests. To prevent it
    // persisting into other tests we must use Factory::create() to reset it.
    // @phpstan-ignore-next-line
    Loop::set(Factory::create());
  }

  public static function setEnvVars($env_vars): void {
    foreach ($env_vars as $key => $value) {
      putenv($key . '=' . $value);
    }
  }

  public static function unsetEnvVars($env_vars): void {
    foreach ($env_vars as $key => $value) {
      putenv($key);
    }
  }

  protected function setIo($input, $output) {
    $this->input = $input;
    $this->output = $output;
    $this->logger = new ConsoleLogger($output);
    $this->localMachineHelper = new LocalMachineHelper($input, $output, $this->logger);
    // TTY should never be used for tests.
    $this->localMachineHelper->setIsTty(FALSE);
    $this->sshHelper = new SshHelper($output, $this->localMachineHelper, $this->logger);
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
   * Returns a mock response from acquia-spec.yaml.
   *
   * This assumes you want a JSON or HTML response. If you want something less
   * common (i.e. an octet-stream for file downloads), don't use this method.
   *
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
    elseif (array_key_exists('example', $content)) {
      $response_body = json_encode($content['example']);
    }
    elseif (array_key_exists('schema', $content)
      && array_key_exists('$ref', $content['schema'])) {
      $ref = $content['schema']['$ref'];
      $param_key = str_replace('#/components/schemas/', '', $ref);
      $spec = $this->getCloudApiSpec();
      return (object) $spec['components']['schemas'][$param_key]['properties'];
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
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->acliRepoRoot,
      $this->clientServiceProphecy->reveal(),
      $this->logStreamManagerProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir,
      $this->logger
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
    $acquia_cloud_spec_file = $this->apiSpecFixtureFilePath;
    $acquia_cloud_spec_file_checksum = md5_file($acquia_cloud_spec_file);

    $cache_key = basename($acquia_cloud_spec_file);
    $cache = new PhpArrayAdapter(__DIR__ . '/../../../var/cache/' . $cache_key . '.cache', new FilesystemAdapter());
    $is_command_cache_valid = $this->isApiSpecCacheValid($cache, $cache_key, $acquia_cloud_spec_file_checksum);
    $api_spec_cache_item = $cache->getItem($cache_key);
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
  private function isApiSpecCacheValid(PhpArrayAdapter $cache, $cache_key, $acquia_cloud_spec_file_checksum): bool {
    $api_spec_checksum_item = $cache->getItem($cache_key . '.checksum');
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
    $private_key_filepath = $this->fs->tempnam($this->sshDir, 'acli');
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

  protected function mockPermissionsRequest($application_response, $perms = TRUE) {
    $permissions_response = $this->getMockResponseFromSpec("/applications/{applicationUuid}/permissions",
      'get', '200');
    if (!$perms) {
      $delete_perms = [
        'add ssh key to git',
        'add ssh key to non-prod',
        'add ssh key to prod',
      ];
      foreach ($permissions_response->_embedded->items as $index => $item) {
        if (in_array($item->name, $delete_perms, TRUE)) {
          unset($permissions_response->_embedded->items[$index]);
        }
      }
    }
    $this->clientProphecy->request('get',
      '/applications/' . $application_response->uuid . '/permissions')
      ->willReturn($permissions_response->_embedded->items)
      ->shouldBeCalled();

    return $permissions_response;
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
    $response = $this->getMockEnvironmentsResponse();
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn($response->_embedded->items)
      ->shouldBeCalled();

    return $response;
  }

  /**
   * Request account information.
   *
   * @param bool $support
   *   Whether the account should have the support flag.
   */
  protected function mockAccountRequest($support = FALSE): void {
    $account = $this->getMockResponseFromSpec('/account', 'get', 200);
    if ($support) {
      $account->flags->support = TRUE;
      $this->clientProphecy->addQuery('all', 'true')->shouldBeCalled();
    }
    $this->clientProphecy->request('get', '/account')->willReturn($account);
  }

  /**
   * @param string $method
   *
   * @param string $http_code
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getMockEnvironmentResponse(string $method = 'get', string $http_code = '200'): object {
    return $this->getMockResponseFromSpec('/environments/{environmentId}',
      $method, $http_code);
  }

  /**
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getMockEnvironmentsResponse() {
    $environments_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/environments',
      'get', 200);

    return $environments_response;
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
   * @param LocalMachineHelper|ObjectProphecy $local_machine_helper
   * @param Filesystem|ObjectProphecy $file_system
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function mockGenerateSshKey($local_machine_helper, $file_system) {
    $key_contents = 'thekey!';
    $public_key_path = 'id_rsa.pub';
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getOutput()->willReturn($key_contents);
    $local_machine_helper->checkRequiredBinariesExist(["ssh-keygen"])->shouldBeCalled();
    $local_machine_helper->execute(Argument::withEntry(0, 'ssh-keygen'), NULL, NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $local_machine_helper->readFile($public_key_path)->willReturn($key_contents);
    $local_machine_helper->readFile(Argument::containingString('id_rsa'))->willReturn($key_contents);
  }

  /**
   * @param $local_machine_helper
   */
  protected function mockAddSshKeyToAgent($local_machine_helper, $file_system) {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $local_machine_helper->executeFromCmd(Argument::containingString('SSH_PASS'), NULL, NULL, FALSE)->willReturn($process->reveal());
    $file_system->tempnam(Argument::type('string'), 'acli')->willReturn('something');
    $file_system->chmod('something', 493)->shouldBeCalled();
    $file_system->remove('something')->shouldBeCalled();
    $local_machine_helper->writeFile('something', Argument::type('string'))->shouldBeCalled();
  }

  /**
   * @param LocalMachineHelper|ObjectProphecy $local_machine_helper
   * @param bool $success
   */
  protected function mockSshAgentList($local_machine_helper, $success = FALSE): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn($success);
    $process->getExitCode()->willReturn($success ? 0 : 1);
    $process->getOutput()->willReturn('thekey!');
    $local_machine_helper->getLocalFilepath($this->passphraseFilepath)
      ->willReturn('/tmp/.passphrase');
    $local_machine_helper->execute([
      'ssh-add',
      '-L',
    ], NULL, NULL, FALSE)->shouldBeCalled()->willReturn($process->reveal());
  }

  /**
   *
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
  protected function mockStartPhp(ObjectProphecy $local_machine_helper): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $local_machine_helper->execute([
      'supervisorctl',
      'start',
      'php-fpm',
    ], NULL, NULL, FALSE)->willReturn($process->reveal())->shouldBeCalled();
    return $process;
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockStopPhp(ObjectProphecy $local_machine_helper): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $local_machine_helper->execute([
      'supervisorctl',
      'stop',
      'php-fpm',
    ], NULL, NULL, FALSE)->willReturn($process->reveal())->shouldBeCalled();
    return $process;
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
   * @param \Prophecy\Prophecy\ObjectProphecy|LocalMachineHelper $local_machine_helper
   *
   * @return Filesystem
   */
  protected function mockGetFilesystem(ObjectProphecy $local_machine_helper) {
    $local_machine_helper->getFilesystem()->willReturn($this->fs)->shouldBeCalled();

    return $this->fs;
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

  protected function setClientProphecies($client_service_class = ClientService::class): void {
    $this->clientProphecy = $this->prophet->prophesize(Client::class);
    $this->clientProphecy->addOption('headers', ['User-Agent' => 'acli/UNKNOWN']);
    $this->clientProphecy->addOption('debug', Argument::type(OutputInterface::class));
    $this->clientServiceProphecy = $this->prophet->prophesize($client_service_class);
    $this->clientServiceProphecy->getClient()
      ->willReturn($this->clientProphecy->reveal());
    $this->clientServiceProphecy->isMachineAuthenticated(Argument::type(CloudDataStore::class))
      ->willReturn(TRUE);
  }

  protected function createDataStores(): void {
    $this->datastoreAcli = new AcquiaCliDatastore($this->localMachineHelper, new AcquiaCliConfig(), $this->acliConfigFilepath);
    $this->datastoreCloud = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
  }

}
