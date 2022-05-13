<?php

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\CommandFactoryInterface;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaLogstream\LogstreamManager;
use Psr\Log\LoggerInterface;

/**
 * Class ApiCommandFactory.
 */
class AcsfCommandFactory implements CommandFactoryInterface {

  /**
   * @var \Acquia\Cli\Helpers\LocalMachineHelper
   */
  private $localMachineHelper;

  /**
   * @var \Acquia\Cli\DataStore\CloudDataStore
   */
  private $datastoreCloud;

  /**
   * @var \Acquia\Cli\DataStore\YamlStore
   */
  private $datastoreAcli;

  /**
   * @var \Acquia\Cli\AcsfApi\AcsfCredentials
   */
  private $cloudCredentials;

  /**
   * @var \Acquia\Cli\Helpers\TelemetryHelper
   */
  private $telemetryHelper;

  /**
   * @var string
   */
  private string $repoRoot;

  /**
   * @var \Acquia\Cli\AcsfApi\AcsfClientService
   */
  private $cloudApiClientService;

  /**
   * @var \AcquiaLogstream\LogstreamManager
   */
  private $logstreamManager;

  /**
   * @var \Acquia\Cli\Helpers\SshHelper
   */
  private $sshHelper;

  /**
   * @var string
   */
  private string $sshDir;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * @param \Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   * @param \Acquia\Cli\DataStore\CloudDataStore $datastoreCloud
   * @param \Acquia\Cli\DataStore\AcquiaCliDatastore $datastoreAcli
   * @param \Acquia\Cli\AcsfApi\AcsfCredentials $cloudCredentials
   * @param \Acquia\Cli\Helpers\TelemetryHelper $telemetryHelper
   * @param string $repoRoot
   * @param \Acquia\Cli\AcsfApi\AcsfClientService $cloudApiClientService
   * @param \AcquiaLogstream\LogstreamManager $logstreamManager
   * @param \Acquia\Cli\Helpers\SshHelper $sshHelper
   * @param string $sshDir
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(
    LocalMachineHelper $localMachineHelper,
    CloudDataStore $datastoreCloud,
    AcquiaCliDatastore $datastoreAcli,
    AcsfCredentials $cloudCredentials,
    TelemetryHelper $telemetryHelper,
    string $repoRoot,
    AcsfClientService $cloudApiClientService,
    LogstreamManager $logstreamManager,
    SshHelper $sshHelper,
    string $sshDir,
    LoggerInterface $logger
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
      $this->repoRoot,
      $this->cloudApiClientService,
      $this->logstreamManager,
      $this->sshHelper,
      $this->sshDir,
      $this->logger
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
      $this->repoRoot,
      $this->cloudApiClientService,
      $this->logstreamManager,
      $this->sshHelper,
      $this->sshDir,
      $this->logger
    );
  }

}
