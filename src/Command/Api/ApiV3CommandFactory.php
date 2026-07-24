<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\CloudApi\V3ClientService;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use Psr\Log\LoggerInterface;
use SelfUpdate\SelfUpdateManager;

/**
 * Command factory for Cloud API v3 (MEO) specs. Differs from ApiCommandFactory
 * only by injecting V3ClientService (which resolves its base URI via
 * CloudCredentials::getV3BaseUri()). All other dependencies are shared with v2.
 */
class ApiV3CommandFactory extends ApiCommandFactory
{
    public function __construct(
        LocalMachineHelper $localMachineHelper,
        CloudDataStore $datastoreCloud,
        AcquiaCliDatastore $datastoreAcli,
        CloudCredentials $cloudCredentials,
        TelemetryHelper $telemetryHelper,
        string $projectDir,
        V3ClientService $cloudApiClientService,
        SshHelper $sshHelper,
        string $sshDir,
        LoggerInterface $logger,
        SelfUpdateManager $selfUpdateManager,
    ) {
        parent::__construct(
            $localMachineHelper,
            $datastoreCloud,
            $datastoreAcli,
            $cloudCredentials,
            $telemetryHelper,
            $projectDir,
            $cloudApiClientService,
            $sshHelper,
            $sshDir,
            $logger,
            $selfUpdateManager,
        );
    }
}
