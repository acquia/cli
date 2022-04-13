<?php

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\Command\ApiCommandBase;
use Acquia\Cli\DataStore\YamlStore;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaLogstream\LogstreamManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * Class ApiCommandBase.
 *
 */
class AcsfBaseCommand extends ApiCommandBase {

  protected static $defaultName = 'acsf:base';

  /**
   * @var \Acquia\Cli\AcsfApi\AcsfClientService
   */
  protected AcsfClientService $acsfClientService;

  /**
   * CommandBase constructor.
   *
   * @param string $cloudConfigFilepath
   * @param LocalMachineHelper $localMachineHelper
   * @param JsonFileStore $datastoreCloud
   * @param YamlStore $datastoreAcli
   * @param CloudCredentials $cloudCredentials
   * @param TelemetryHelper $telemetryHelper
   * @param string $acliConfigFilepath
   * @param string $repoRoot
   * @param AcsfClientService $acsfClientService
   * @param ConsoleLogger $logger
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
    AcsfClientService $acsfClientService,
    LoggerInterface $logger
  ) {
    $this->datastoreCloud = $datastoreCloud;
    $this->cloudCredentials = $cloudCredentials;
    $this->acsfClientService = $acsfClientService;

    parent::__construct(
      $cloudConfigFilepath,
      $localMachineHelper,
      $datastoreCloud,
      $datastoreAcli,
      $cloudCredentials,
      $telemetryHelper,
      $acliConfigFilepath,
      $repoRoot,
      $this->acsfClientService,
      $logstreamManager,
      $sshHelper,
      $sshDir,
      $logger
    );
  }
}