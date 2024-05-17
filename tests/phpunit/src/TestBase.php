<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\Config\AcquiaCliConfig;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Exception\ApiErrorException;
use Closure;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophet;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
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
use Symfony\Component\Process\Process;

/**
 * @property \Acquia\Cli\Command\CommandBase $command
 */
abstract class TestBase extends TestCase {

  protected string $apiSpecFixtureFilePath = __DIR__ . '/../../../assets/acquia-spec.json';

  protected ConsoleOutput $consoleOutput;

  protected Filesystem $fs;

  protected Prophet $prophet;

  protected string $projectDir;

  protected string $fixtureDir;

  protected Application $application;

  protected ArrayInput $input;

  protected OutputInterface $output;

  protected Client|ObjectProphecy $clientProphecy;

  /**
   * @var array<mixed>
   */
  protected array $acliConfig = [];

  /**
   * @var array<mixed>
   */
  protected array $cloudConfig = [];

  protected string $key = '17feaf34-5d04-402b-9a67-15d5161d24e1';

  protected string $secret = 'X1u\/PIQXtYaoeui.4RJSJpGZjwmWYmfl5AUQkAebYE=';

  protected string $dataDir;

  protected string $cloudConfigFilepath;

  protected string $acliConfigFilepath;

  protected AcquiaCliDatastore $datastoreAcli;

  protected CloudDataStore $datastoreCloud;

  protected ApiCredentialsInterface $cloudCredentials;

  protected LocalMachineHelper $localMachineHelper;

  protected TelemetryHelper $telemetryHelper;

  protected string $acliConfigFilename;

  protected ClientService|ObjectProphecy $clientServiceProphecy;

  protected SshHelper $sshHelper;

  protected string $sshDir;

  protected string $acliRepoRoot;

  protected ConsoleLogger $logger;

  protected string $passphraseFilepath = '~/.passphrase';

  protected vfsStreamDirectory $vfsRoot;

  protected string $realFixtureDir;

  /**
   * Filter an applications response in order to simulate query filters.
   *
   * The CXAPI spec returns two sample applications with identical hosting ids.
   * While hosting ids are not guaranteed to be unique, in practice they are
   * unique. This renames one of the applications to be unique.
   *
   * @see CXAPI-9647
   */
  public function filterApplicationsResponse(object $applicationsResponse, int $count, bool $unique): object {
    if ($unique) {
      $applicationsResponse->{'_embedded'}->items[1]->hosting->id = 'devcloud:devcloud3';
    }
    $applicationsResponse->total = $count;
    $applicationsResponse->{'_embedded'}->items = array_slice($applicationsResponse->{'_embedded'}->items, 0, $count);
    return $applicationsResponse;
  }

  /**
   * @todo get rid of this method and use virtual file systems (setupVfsFixture)
   */
  public function setupFsFixture(): void {
    $this->fixtureDir = $this->getTempDir();
    $this->fs->mirror(realpath(__DIR__ . '/../../fixtures'), $this->fixtureDir);
    $this->projectDir = $this->fixtureDir . '/project';
    $this->acliRepoRoot = $this->projectDir;
    $this->dataDir = $this->fixtureDir . '/.acquia';
    $this->sshDir = $this->getTempDir();
    $this->acliConfigFilename = '.acquia-cli.yml';
    $this->cloudConfigFilepath = $this->dataDir . '/cloud_api.conf';
    $this->acliConfigFilepath = $this->projectDir . '/' . $this->acliConfigFilename;
    $this->createMockConfigFiles();
    $this->createDataStores();
    $this->cloudCredentials = new CloudCredentials($this->datastoreCloud);
    $this->telemetryHelper = new TelemetryHelper($this->clientServiceProphecy->reveal(), $this->datastoreCloud, $this->application);
    chdir($this->projectDir);
  }

  /**
   * This method is called before each test.
   */
  protected function setUp(): void {
    self::setEnvVars([
      'COLUMNS' => '85',
      'HOME' => '/home/test',
    ]);
    $this->output = new BufferedOutput();
    $this->input = new ArrayInput([]);

    $this->application = new Application();
    $this->fs = new Filesystem();
    $this->prophet = new Prophet();
    $this->consoleOutput = new ConsoleOutput();
    $this->setClientProphecies();
    $this->setIo();

    $this->vfsRoot = vfsStream::setup();
    $this->projectDir = vfsStream::newDirectory('project')->at($this->vfsRoot)->url();
    $this->sshDir = vfsStream::newDirectory('ssh')->at($this->vfsRoot)->url();
    $this->dataDir = vfsStream::newDirectory('.acquia')->at($this->vfsRoot)->url();
    $this->cloudConfigFilepath = Path::join($this->dataDir, 'cloud_api.conf');
    $this->acliConfigFilename = '.acquia-cli.yml';
    $this->acliConfigFilepath = Path::join($this->projectDir, $this->acliConfigFilename);
    $this->acliRepoRoot = $this->projectDir;
    $this->createMockConfigFiles();
    $this->createDataStores();
    $this->cloudCredentials = new CloudCredentials($this->datastoreCloud);
    $this->telemetryHelper = new TelemetryHelper($this->clientServiceProphecy->reveal(), $this->datastoreCloud, $this->application);

    $this->realFixtureDir = realpath(Path::join(__DIR__, '..', '..', 'fixtures'));

    parent::setUp();
  }

  /**
   * Create a guaranteed-unique temporary directory.
   */
  protected function getTempDir(): string {
    // sys_get_temp_dir() is not thread-safe, but it's okay to use here since we are specifically creating a thread-safe temporary directory.
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
      throw new AcquiaCliException('Cannot write to temporary directory');
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

  public static function setEnvVars(array $envVars): void {
    foreach ($envVars as $key => $value) {
      putenv($key . '=' . $value);
    }
  }

  public static function unsetEnvVars(array $envVars): void {
    foreach ($envVars as $key => $value) {
      if (is_int($key)) {
        putenv($value);
      }
      else {
        putenv($key);
      }
    }
  }

  private function setIo(): void {
    $this->logger = new ConsoleLogger($this->output);
    $this->localMachineHelper = new LocalMachineHelper($this->input, $this->output, $this->logger);
    // TTY should never be used for tests.
    $this->localMachineHelper->setIsTty(FALSE);
    $this->sshHelper = new SshHelper($this->output, $this->localMachineHelper, $this->logger);
  }

  protected function getResourceFromSpec(mixed $path, mixed $method): mixed {
    $acquiaCloudSpec = $this->getCloudApiSpec();
    return $acquiaCloudSpec['paths'][$path][$method];
  }

  /**
   * Returns a mock response from acquia-spec.yaml.
   *
   * This assumes you want a JSON or HTML response. If you want something less
   * common (i.e. an octet-stream for file downloads), don't use this method.
   *
   * @param $path
   * @param $method
   * @param $httpCode
   * @see CXAPI-7208
   */
  public function getMockResponseFromSpec(mixed $path, mixed $method, mixed $httpCode): object {
    $endpoint = $this->getResourceFromSpec($path, $method);
    $response = $endpoint['responses'][$httpCode];
    if (array_key_exists('application/hal+json', $response['content'])) {
      $content = $response['content']['application/hal+json'];
    }
    else {
      $content = $response['content']['application/json'];
    }

    if (array_key_exists('example', $content)) {
      $responseBody = json_encode($content['example'], JSON_THROW_ON_ERROR);
    }
    elseif (array_key_exists('examples', $content)) {
      $responseBody = json_encode($content['examples'], JSON_THROW_ON_ERROR);
    }
    elseif (array_key_exists('schema', $content)
      && array_key_exists('$ref', $content['schema'])) {
      $ref = $content['schema']['$ref'];
      $paramKey = str_replace('#/components/schemas/', '', $ref);
      $spec = $this->getCloudApiSpec();
      return (object) $spec['components']['schemas'][$paramKey]['properties'];
    }
    else {
      return (object) [];
    }

    return json_decode($responseBody, FALSE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * @return array<mixed>
   */
  protected function getPathMethodCodeFromSpec(string $operationId): array {
    $acquiaCloudSpec = $this->getCloudApiSpec();
    foreach ($acquiaCloudSpec['paths'] as $path => $methodEndpoint) {
      foreach ($methodEndpoint as $method => $endpoint) {
        if ($endpoint['operationId'] === $operationId) {
          foreach ($endpoint['responses'] as $code => $response) {
            if ($code >= 200 && $code < 300) {
              return [$path, $method, $code];
            }
          }
        }
      }
    }
    throw new \Exception('operationId not found');
  }

  /**
   * Build and return a command with common dependencies.
   *
   * All commands inherit from a common base and use the same constructor with a
   * bunch of dependencies injected. It would be tedious for every command test
   * to inject every dependency as part of createCommand(). They can use this
   * instead.
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
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
    );
  }

  public function getMockRequestBodyFromSpec(string $path, string $method = 'post'): mixed {
    $endpoint = $this->getResourceFromSpec($path, $method);
    if (array_key_exists('application/json', $endpoint['requestBody']['content'])) {
      return $endpoint['requestBody']['content']['application/json']['example'];
    }

    return $endpoint['requestBody']['content']['application/hal+json']['example'];
  }

  protected function getCloudApiSpec(): mixed {
    // We cache the yaml file because it's 20k+ lines and takes FOREVER
    // to parse when xDebug is enabled.
    $acquiaCloudSpecFile = $this->apiSpecFixtureFilePath;
    $acquiaCloudSpecFileChecksum = md5_file($acquiaCloudSpecFile);

    $cacheKey = basename($acquiaCloudSpecFile);
    $cache = new PhpArrayAdapter(__DIR__ . '/../../../var/cache/' . $cacheKey . '.cache', new FilesystemAdapter());
    $isCommandCacheValid = $this->isApiSpecCacheValid($cache, $cacheKey, $acquiaCloudSpecFileChecksum);
    $apiSpecCacheItem = $cache->getItem($cacheKey);
    if ($isCommandCacheValid && $apiSpecCacheItem->isHit()) {
      return $apiSpecCacheItem->get();
    }
    $apiSpec = json_decode(file_get_contents($acquiaCloudSpecFile), TRUE);
    $this->saveApiSpecCacheItems($cache, $acquiaCloudSpecFileChecksum, $apiSpecCacheItem, $apiSpec);

    return $apiSpec;
  }

  private function isApiSpecCacheValid(PhpArrayAdapter $cache, mixed $cacheKey, string $acquiaCloudSpecFileChecksum): bool {
    $apiSpecChecksumItem = $cache->getItem($cacheKey . '.checksum');
    // If there's an invalid entry OR there's no entry, return false.
    return !(!$apiSpecChecksumItem->isHit() || ($apiSpecChecksumItem->isHit()
        && $apiSpecChecksumItem->get() !== $acquiaCloudSpecFileChecksum));
  }

  private function saveApiSpecCacheItems(
    PhpArrayAdapter $cache,
    string $acquiaCloudSpecFileChecksum,
    CacheItem $apiSpecCacheItem,
    mixed $apiSpec
  ): void {
    $apiSpecChecksumItem = $cache->getItem('api_spec.checksum');
    $apiSpecChecksumItem->set($acquiaCloudSpecFileChecksum);
    $cache->save($apiSpecChecksumItem);
    $apiSpecCacheItem->set($apiSpec);
    $cache->save($apiSpecCacheItem);
  }

  protected function createLocalSshKey(mixed $contents): string {
    $privateKeyFilepath = $this->fs->tempnam($this->sshDir, 'acli');
    $this->fs->touch($privateKeyFilepath);
    $publicKeyFilepath = $privateKeyFilepath . '.pub';
    $this->fs->dumpFile($publicKeyFilepath, $contents);

    return $publicKeyFilepath;
  }

  protected function createMockConfigFiles(): void {
    $this->createMockCloudConfigFile();

    $defaultValues = [];
    $acliConfig = array_merge($defaultValues, $this->acliConfig);
    $contents = json_encode($acliConfig, JSON_THROW_ON_ERROR);
    $filepath = $this->acliConfigFilepath;
    $this->fs->dumpFile($filepath, $contents);
  }

  protected function createMockCloudConfigFile(mixed $defaultValues = []): void {
    if (!$defaultValues) {
      $defaultValues = [
        'acli_key' => $this->key,
        'keys' => [
          (string) ($this->key) => [
            'label' => 'Test Key',
            'secret' => $this->secret,
            'uuid' => $this->key,
          ],
        ],
        DataStoreContract::SEND_TELEMETRY => FALSE,
      ];
    }
    $cloudConfig = array_merge($defaultValues, $this->cloudConfig);
    $contents = json_encode($cloudConfig, JSON_THROW_ON_ERROR);
    $filepath = $this->cloudConfigFilepath;
    $this->fs->dumpFile($filepath, $contents);
  }

  protected function createMockAcliConfigFile(string $cloudAppUuid): void {
    $this->datastoreAcli->set('cloud_app_uuid', $cloudAppUuid);
  }

  /**
   * This is the preferred generic way of mocking requests and responses. We still maintain a lot of boilerplate mocking methods for legacy reasons.
   *
   * Auto-completion and return type inferencing is provided by .phpstorm.meta.php.
   */
  protected function mockRequest(string $operationId, string|array|null $params = NULL, ?array $body = NULL, ?string $exampleResponse = NULL, Closure $tamper = NULL): object|array {
    if (is_string($params)) {
      $params = [$params];
    }
    else if (is_null($params)) {
      $params = [];
    }
    [$path, $method, $code] = $this->getPathMethodCodeFromSpec($operationId);
    if (count($params) !== substr_count($path, '{')) {
      throw new RuntimeException('Invalid number of parameters');
    }
    $response = $this->getMockResponseFromSpec($path, $method, $code);

    // This is a set of example responses.
    if (isset($exampleResponse) && property_exists($response, $exampleResponse)) {
      $response = $response->$exampleResponse->value;
    }
    // This has multiple responses.
    if (property_exists($response, '_embedded') && property_exists($response->_embedded, 'items')) {
      $response = $response->_embedded->items;
    }
    if (isset($tamper)) {
      $tamper($response);
    }
    foreach ($params as $param) {
      $path = preg_replace('/\{\w*}/', $param, $path, 1);
    }
    $this->clientProphecy->request($method, $path, $body)
      ->willReturn($response)
      ->shouldBeCalled();
    return $response;
  }

  /**
   * @param int $count The number of applications to return. Use this to simulate query filters.
   */
  public function mockApplicationsRequest(int $count = 2, bool $unique = TRUE): object {
    // Request for applications.
    $applicationsResponse = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $applicationsResponse = $this->filterApplicationsResponse($applicationsResponse, $count, $unique);
    $this->clientProphecy->request('get', '/applications')
      ->willReturn($applicationsResponse->{'_embedded'}->items)
      ->shouldBeCalled();
    return $applicationsResponse;
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
      'error' => 'some error',
      'message' => 'some error',
    ];
    $this->clientProphecy->request('get', Argument::type('string'))
      ->willThrow(new ApiErrorException($response, $response->message));
  }

  public function mockNoAvailableIdes(): void {
    $response = (object) [
      'error' => "There are no available Cloud IDEs for this application.\n",
      'message' => "There are no available Cloud IDEs for this application.\n",
    ];
    $this->clientProphecy->request('get', Argument::type('string'))
      ->willThrow(new ApiErrorException($response, $response->message));
  }

  protected function mockApplicationRequest(): object {
    $applicationsResponse = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $applicationResponse = $applicationsResponse->{'_embedded'}->items[0];
    $this->clientProphecy->request('get',
      '/applications/' . $applicationsResponse->{'_embedded'}->items[0]->uuid)
      ->willReturn($applicationResponse)
      ->shouldBeCalled();

    return $applicationResponse;
  }

  protected function mockPermissionsRequest(mixed $applicationResponse, mixed $perms = TRUE): object {
    $permissionsResponse = $this->getMockResponseFromSpec("/applications/{applicationUuid}/permissions",
      'get', '200');
    if (!$perms) {
      $deletePerms = [
        'add ssh key to git',
        'add ssh key to non-prod',
        'add ssh key to prod',
      ];
      foreach ($permissionsResponse->_embedded->items as $index => $item) {
        if (in_array($item->name, $deletePerms, TRUE)) {
          unset($permissionsResponse->_embedded->items[$index]);
        }
      }
    }
    $this->clientProphecy->request('get',
      '/applications/' . $applicationResponse->uuid . '/permissions')
      ->willReturn($permissionsResponse->_embedded->items)
      ->shouldBeCalled();

    return $permissionsResponse;
  }

  public function mockEnvironmentsRequest(
    object $applicationsResponse
  ): object {
    $response = $this->getMockEnvironmentsResponse();
    $this->clientProphecy->request('get',
      "/applications/{$applicationsResponse->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn($response->_embedded->items)
      ->shouldBeCalled();

    return $response;
  }

  protected function getMockEnvironmentResponse(string $method = 'get', string $httpCode = '200'): object {
    return $this->getMockResponseFromSpec('/environments/{environmentId}',
      $method, $httpCode);
  }

  protected function getMockEnvironmentsResponse(): object {
    return $this->getMockResponseFromSpec('/applications/{applicationUuid}/environments',
      'get', 200);
  }

  /**
   * @return array<mixed>
   */
  protected function mockListSshKeysRequest(): array {
    return $this->mockRequest('getAccountSshKeys');
  }

  protected function mockListSshKeysRequestWithIdeKey(string $ideLabel, string $ideUuid): object {
    $mockBody = $this->getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
    $mockBody->{'_embedded'}->items[0]->label = preg_replace('/\W/', '', 'IDE_' . $ideLabel . '_' . $ideUuid);
    $this->clientProphecy->request('get', '/account/ssh-keys')
      ->willReturn($mockBody->{'_embedded'}->items)
      ->shouldBeCalled();
    return $mockBody;
  }

  protected function mockGenerateSshKey(ObjectProphecy|LocalMachineHelper $localMachineHelper, ?string $keyContents = NULL): void {
    $keyContents = $keyContents ?: 'thekey!';
    $publicKeyPath = 'id_rsa.pub';
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getOutput()->willReturn($keyContents);
    $localMachineHelper->checkRequiredBinariesExist(["ssh-keygen"])->shouldBeCalled();
    $localMachineHelper->execute(Argument::withEntry(0, 'ssh-keygen'), NULL, NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $localMachineHelper->readFile($publicKeyPath)->willReturn($keyContents);
    $localMachineHelper->readFile(Argument::containingString('id_rsa'))->willReturn($keyContents);
  }

  protected function mockAddSshKeyToAgent(mixed $localMachineHelper, mixed $fileSystem): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $localMachineHelper->executeFromCmd(Argument::containingString('SSH_PASS'), NULL, NULL, FALSE)->willReturn($process->reveal());
    $fileSystem->tempnam(Argument::type('string'), 'acli')->willReturn('something');
    $fileSystem->chmod('something', 493)->shouldBeCalled();
    $fileSystem->remove('something')->shouldBeCalled();
    $localMachineHelper->writeFile('something', Argument::type('string'))->shouldBeCalled();
  }

  protected function mockSshAgentList(ObjectProphecy|LocalMachineHelper $localMachineHelper, bool $success = FALSE): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn($success);
    $process->getExitCode()->willReturn($success ? 0 : 1);
    $process->getOutput()->willReturn('thekey!');
    $localMachineHelper->getLocalFilepath($this->passphraseFilepath)
      ->willReturn('/tmp/.passphrase');
    $localMachineHelper->execute([
      'ssh-add',
      '-L',
    ], NULL, NULL, FALSE)->shouldBeCalled()->willReturn($process->reveal());
  }

  protected function mockDeleteSshKeyRequest(string $keyUuid): void {
    $this->mockRequest('deleteAccountSshKey', $keyUuid, NULL, 'Removed key');
  }

  protected function mockListSshKeyRequestWithUploadedKey(
    mixed $mockRequestArgs
  ): void {
    $mockBody = $this->getMockResponseFromSpec('/account/ssh-keys', 'get',
      '200');
    $newItem = array_merge((array) $mockBody->_embedded->items[2], $mockRequestArgs);
    $mockBody->_embedded->items[3] = (object) $newItem;
    $this->clientProphecy->request('get', '/account/ssh-keys')
      ->willReturn($mockBody->{'_embedded'}->items)
      ->shouldBeCalled();
  }

  protected function mockStartPhp(ObjectProphecy|LocalMachineHelper $localMachineHelper): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $localMachineHelper->execute([
      'supervisorctl',
      'start',
      'php-fpm',
    ], NULL, NULL, FALSE)->willReturn($process->reveal())->shouldBeCalled();
    return $process;
  }

  protected function mockStopPhp(ObjectProphecy|LocalMachineHelper $localMachineHelper): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $localMachineHelper->execute([
      'supervisorctl',
      'stop',
      'php-fpm',
    ], NULL, NULL, FALSE)->willReturn($process->reveal())->shouldBeCalled();
    return $process;
  }

  protected function mockRestartPhp(ObjectProphecy|LocalMachineHelper $localMachineHelper): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $localMachineHelper->execute([
      'supervisorctl',
      'restart',
      'php-fpm',
    ], NULL, NULL, FALSE)->willReturn($process->reveal())->shouldBeCalled();
    return $process;
  }

  /**
   * @return \Prophecy\Prophecy\ObjectProphecy|\Symfony\Component\Filesystem\Filesystem
   */
  protected function mockGetFilesystem(ObjectProphecy|LocalMachineHelper $localMachineHelper): ObjectProphecy|Filesystem {
    $localMachineHelper->getFilesystem()->willReturn($this->fs)->shouldBeCalled();

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

  public function mockGuzzleClientForUpdate(array $releases): ObjectProphecy {
    $stream = $this->prophet->prophesize(StreamInterface::class);
    $stream->getContents()->willReturn(json_encode($releases));
    $response = $this->prophet->prophesize(Response::class);
    $response->getBody()->willReturn($stream->reveal());
    $guzzleClient = $this->prophet->prophesize(\GuzzleHttp\Client::class);
    $guzzleClient->request('GET', Argument::containingString('https://api.github.com/repos'), Argument::type('array'))
      ->willReturn($response->reveal());

    $stream = $this->prophet->prophesize(StreamInterface::class);
    $pharContents = file_get_contents(Path::join($this->fixtureDir, 'test.phar'));
    $stream->getContents()->willReturn($pharContents);
    $response = $this->prophet->prophesize(Response::class);
    $response->getBody()->willReturn($stream->reveal());
    $guzzleClient->request('GET', 'https://github.com/acquia/cli/releases/download/v1.0.0-beta3/acli.phar',
      Argument::type('array'))->willReturn($response->reveal());

    return $guzzleClient;
  }

  protected function setClientProphecies(): void {
    $this->clientProphecy = $this->prophet->prophesize(Client::class);
    $this->clientProphecy->addOption('headers', ['User-Agent' => 'acli/UNKNOWN']);
    $this->clientProphecy->addOption('debug', Argument::type(OutputInterface::class));
    $this->clientServiceProphecy = $this->prophet->prophesize(ClientService::class);
    $this->clientServiceProphecy->getClient()
      ->willReturn($this->clientProphecy->reveal());
    $this->clientServiceProphecy->isMachineAuthenticated()
      ->willReturn(TRUE);
  }

  protected function createDataStores(): void {
    $this->datastoreAcli = new AcquiaCliDatastore($this->localMachineHelper, new AcquiaCliConfig(), $this->acliConfigFilepath);
    $this->datastoreCloud = new CloudDataStore($this->localMachineHelper, new CloudDataConfig(), $this->cloudConfigFilepath);
  }

}
