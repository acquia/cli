<?php

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
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
class AcsfCommandFactory {

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
   *
   * @return \Acquia\Cli\Command\Acsf\AcsfApiBaseCommand
   */
  public function __invoke(
    string $cloudConfigFilepath,
    LocalMachineHelper $localMachineHelper,
    JsonFileStore $datastoreCloud,
    YamlStore $datastoreAcli,
    CloudCredentials $cloudCredentials,
    TelemetryHelper $telemetryHelper,
    string $acliConfigFilepath,
    string $repoRoot,
    AcsfClientService $cloudApiClientService,
    LogstreamManager $logstreamManager,
    SshHelper $sshHelper,
    string $sshDir,
    LoggerInterface $logger
  ): AcsfApiBaseCommand {
    return new AcsfApiBaseCommand(
      $cloudConfigFilepath,
      $localMachineHelper,
      $datastoreCloud,
      $datastoreAcli,
      $cloudCredentials,
      $telemetryHelper,
      $acliConfigFilepath,
      $repoRoot,
      $cloudApiClientService,
      $logstreamManager,
      $sshHelper,
      $sshDir,
      $logger
    );
  }
}