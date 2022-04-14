<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\AcsfApi\AcsfCredentials;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\CommandFactoryInterface;
use Acquia\Cli\DataStore\YamlStore;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaLogstream\LogstreamManager;
use Psr\Log\LoggerInterface;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * Class ApiCommandFactory.
 */
class ApiCommandFactory implements CommandFactoryInterface {

  /**
   * @var string
   */
  private string $cloudConfigFilepath;

  /**
   * @var \Acquia\Cli\Helpers\LocalMachineHelper
   */
  private LocalMachineHelper $localMachineHelper;

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  private JsonFileStore $datastoreCloud;

  /**
   * @var \Acquia\Cli\DataStore\YamlStore
   */
  private YamlStore $datastoreAcli;

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
   * @var string
   */
  private string $acliConfigFilepath;

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

  /**
   * @param string $cloudConfigFilepath
   * @param \Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   * @param \Webmozart\KeyValueStore\JsonFileStore $datastoreCloud
   * @param \Acquia\Cli\DataStore\YamlStore $datastoreAcli
   * @param \Acquia\Cli\CloudApi\CloudCredentials $cloudCredentials
   * @param \Acquia\Cli\Helpers\TelemetryHelper $telemetryHelper
   * @param string $acliConfigFilepath
   * @param string $repoRoot
   * @param \Acquia\Cli\CloudApi\ClientService $cloudApiClientService
   * @param \AcquiaLogstream\LogstreamManager $logstreamManager
   * @param \Acquia\Cli\Helpers\SshHelper $sshHelper
   * @param string $sshDir
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(
    string $cloudConfigFilepath,
    LocalMachineHelper $localMachineHelper,
    JsonFileStore $datastoreCloud,
    YamlStore $datastoreAcli,
    CloudCredentials $cloudCredentials,
    TelemetryHelper $telemetryHelper,
    string $acliConfigFilepath,
    string $repoRoot,
    ClientService $cloudApiClientService,
    LogstreamManager $logstreamManager,
    SshHelper $sshHelper,
    string $sshDir,
    LoggerInterface $logger
  ) {
    $this->cloudConfigFilepath = $cloudConfigFilepath;
    $this->localMachineHelper = $localMachineHelper;
    $this->datastoreCloud = $datastoreCloud;
    $this->datastoreAcli = $datastoreAcli;
    $this->cloudCredentials = $cloudCredentials;
    $this->telemetryHelper = $telemetryHelper;
    $this->acliConfigFilepath = $acliConfigFilepath;
    $this->repoRoot = $repoRoot;
    $this->cloudApiClientService = $cloudApiClientService;
    $this->logstreamManager = $logstreamManager;
    $this->sshHelper = $sshHelper;
    $this->sshDir = $sshDir;
    $this->logger = $logger;
  }

  /**
   * @return \Acquia\Cli\Command\Api\ApiBaseCommand
   */
  public function createCommand(): ApiBaseCommand {
    return new ApiBaseCommand(
      $this->cloudConfigFilepath,
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->acliConfigFilepath,
      $this->repoRoot,
      $this->cloudApiClientService,
      $this->logstreamManager,
      $this->sshHelper,
      $this->sshDir,
      $this->logger
    );
  }

  /**
   * @return \Acquia\Cli\Command\Api\ApiListCommand
   */
  public function createListCommand(): ApiListCommand {
    return new ApiListCommand(
      $this->cloudConfigFilepath,
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->acliConfigFilepath,
      $this->repoRoot,
      $this->cloudApiClientService,
      $this->logstreamManager,
      $this->sshHelper,
      $this->sshDir,
      $this->logger
    );
  }

}