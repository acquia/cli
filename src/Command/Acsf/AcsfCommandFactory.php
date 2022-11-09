<?php

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfCredentials;
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
class AcsfCommandFactory implements CommandFactoryInterface {

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
   * @var \Acquia\Cli\AcsfApi\AcsfCredentials
   */
  private AcsfCredentials $cloudCredentials;

  /**
   * @var \Acquia\Cli\Helpers\TelemetryHelper
   */
  private TelemetryHelper $telemetryHelper;

  /**
   * @var string
   */
  private string $projectDir;

  /**
   * @var \Acquia\Cli\AcsfApi\AcsfClientService
   */
  private AcsfClientService $cloudApiClientService;

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
   * @param \Acquia\Cli\AcsfApi\AcsfCredentials $cloudCredentials
   * @param \Acquia\Cli\Helpers\TelemetryHelper $telemetryHelper
   * @param string $projectDir
   * @param \Acquia\Cli\AcsfApi\AcsfClientService $cloudApiClientService
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
    AcsfCredentials $cloudCredentials,
    TelemetryHelper $telemetryHelper,
    string $projectDir,
    AcsfClientService $cloudApiClientService,
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
    $this->projectDir = $projectDir;
    $this->cloudApiClientService = $cloudApiClientService;
    $this->logstreamManager = $logstreamManager;
    $this->sshHelper = $sshHelper;
    $this->sshDir = $sshDir;
    $this->logger = $logger;
    $this->httpClient = $httpClient;
  }

  /**
   * @return \Acquia\Cli\Command\Acsf\AcsfApiBaseCommand
   */
  public function createCommand(): AcsfApiBaseCommand {
    return new AcsfApiBaseCommand(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->projectDir,
      $this->cloudApiClientService,
      $this->logstreamManager,
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClient
    );
  }

  /**
   * @return \Acquia\Cli\Command\Acsf\AcsfListCommand
   */
  public function createListCommand(): AcsfListCommand {
    return new AcsfListCommand(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->projectDir,
      $this->cloudApiClientService,
      $this->logstreamManager,
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClient
    );
  }

}
