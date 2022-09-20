<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\CommandFactoryInterface;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\DataStore\YamlStore;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaLogstream\LogstreamManager;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Class ApiCommandFactory.
 */
class ApiCommandFactory implements CommandFactoryInterface {

  /**
   * @var \Acquia\Cli\Helpers\LocalMachineHelper
   */
  private LocalMachineHelper $localMachineHelper;

  /**
   * @var \Acquia\Cli\DataStore\CloudDataStore
   */
  private CloudDataStore $datastoreCloud;

  /**
   * @var \Acquia\Cli\DataStore\YamlStore|\Acquia\Cli\DataStore\AcquiaCliDatastore
   */
  private YamlStore|AcquiaCliDatastore $datastoreAcli;

  /**
   * @var CloudCredentials
   */
  private CloudCredentials $cloudCredentials;

  /**
   * @var \Acquia\Cli\Helpers\TelemetryHelper
   */
  private TelemetryHelper $telemetryHelper;

  /**
   * @var string
   */
  private string $repoRoot;

  /**
   * @var \Acquia\Cli\CloudApi\ClientService|\Acquia\Cli\AcsfApi\AcsfClientService
   */
  private ClientService|AcsfClientService $cloudApiClientService;

  /**
   * @var \AcquiaLogstream\LogstreamManager
   */
  private LogstreamManager $logstreamManager;

  /**
   * @var \Acquia\Cli\Helpers\SshHelper
   */
  private SshHelper $sshHelper;

  /**
   * @var string
   */
  private string $sshDir;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  private Client $httpClient;

  /**
   * @param \Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   * @param \Acquia\Cli\DataStore\CloudDataStore $datastoreCloud
   * @param \Acquia\Cli\DataStore\AcquiaCliDatastore $datastoreAcli
   * @param \Acquia\Cli\CloudApi\CloudCredentials $cloudCredentials
   * @param \Acquia\Cli\Helpers\TelemetryHelper $telemetryHelper
   * @param string $repoRoot
   * @param \Acquia\Cli\CloudApi\ClientService $cloudApiClientService
   * @param \AcquiaLogstream\LogstreamManager $logstreamManager
   * @param \Acquia\Cli\Helpers\SshHelper $sshHelper
   * @param string $sshDir
   * @param \Psr\Log\LoggerInterface $logger
   * @param \GuzzleHttp\Client $httpClient
   */
  public function __construct(
    LocalMachineHelper $localMachineHelper,
    CloudDataStore $datastoreCloud,
    AcquiaCliDatastore $datastoreAcli,
    CloudCredentials $cloudCredentials,
    TelemetryHelper $telemetryHelper,
    string $repoRoot,
    ClientService $cloudApiClientService,
    LogstreamManager $logstreamManager,
    SshHelper $sshHelper,
    string $sshDir,
    LoggerInterface $logger,
    Client $httpClient
  ) {
    $this->localMachineHelper = $localMachineHelper;
    $this->datastoreCloud = $datastoreCloud;
    $this->datastoreAcli = $datastoreAcli;
    $this->cloudCredentials = $cloudCredentials;
    $this->telemetryHelper = $telemetryHelper;
    $this->repoRoot = $repoRoot;
    $this->cloudApiClientService = $cloudApiClientService;
    $this->logstreamManager = $logstreamManager;
    $this->sshHelper = $sshHelper;
    $this->sshDir = $sshDir;
    $this->logger = $logger;
    $this->httpClient = $httpClient;
  }

  /**
   * @return \Acquia\Cli\Command\Api\ApiBaseCommand
   */
  public function createCommand(): ApiBaseCommand {
    return new ApiBaseCommand(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->repoRoot,
      $this->cloudApiClientService,
      $this->logstreamManager,
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClient
    );
  }

  /**
   * @return \Acquia\Cli\Command\Api\ApiListCommand
   */
  public function createListCommand(): ApiListCommand {
    return new ApiListCommand(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->repoRoot,
      $this->cloudApiClientService,
      $this->logstreamManager,
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClient
    );
  }

}
