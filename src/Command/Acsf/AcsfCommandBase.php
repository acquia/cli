<?php

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\Command\CommandBase;
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
abstract class AcsfCommandBase extends CommandBase {

}