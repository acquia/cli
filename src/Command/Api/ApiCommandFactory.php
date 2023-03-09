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

  private LocalMachineHelper $localMachineHelper;

  private CloudDataStore $datastoreCloud;

  private YamlStore|AcquiaCliDatastore $datastoreAcli;

  private CloudCredentials $cloudCredentials;

  private TelemetryHelper $telemetryHelper;

  private string $projectDir;

  private ClientService|AcsfClientService $cloudApiClientService;

  private LogstreamManager $logstreamManager;

  private SshHelper $sshHelper;

  private string $sshDir;

  private LoggerInterface $logger;

  private Client $httpClient;

  public function __construct(
    LocalMachineHelper $localMachineHelper,
    CloudDataStore $datastoreCloud,
    AcquiaCliDatastore $datastoreAcli,
    CloudCredentials $cloudCredentials,
    TelemetryHelper $telemetryHelper,
    string $projectDir,
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
    $this->projectDir = $projectDir;
    $this->cloudApiClientService = $cloudApiClientService;
    $this->logstreamManager = $logstreamManager;
    $this->sshHelper = $sshHelper;
    $this->sshDir = $sshDir;
    $this->logger = $logger;
    $this->httpClient = $httpClient;
  }

  public function createCommand(): ApiBaseCommand {
    return new ApiBaseCommand(
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
   */
  public function createListCommand(): ApiListCommand {
    return new ApiListCommand(
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
