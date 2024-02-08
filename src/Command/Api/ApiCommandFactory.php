<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\CommandFactoryInterface;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class ApiCommandFactory implements CommandFactoryInterface {

  public function __construct(
    private LocalMachineHelper $localMachineHelper,
    private CloudDataStore $datastoreCloud,
    private AcquiaCliDatastore $datastoreAcli,
    private CloudCredentials $cloudCredentials,
    private TelemetryHelper $telemetryHelper,
    private string $projectDir,
    private ClientService $cloudApiClientService,
    private SshHelper $sshHelper,
    private string $sshDir,
    private LoggerInterface $logger,
    private Client $httpClient
  ) {
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
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClient
    );
  }

  public function createListCommand(): ApiListCommand {
    return new ApiListCommand(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->projectDir,
      $this->cloudApiClientService,
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClient
    );
  }

}
