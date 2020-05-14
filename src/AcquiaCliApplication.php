<?php

namespace Acquia\Cli;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Helpers\CloudApiDataStoreAwareTrait;
use Acquia\Cli\Helpers\DataStoreAwareTrait;
use Acquia\Cli\Helpers\LocalMachineHelper;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use drupol\phposinfo\Enum\FamilyName;
use drupol\phposinfo\OsInfo;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\KeyValueStore\JsonFileStore;
use Zumba\Amplitude\Amplitude;

/**
 * Class CommandBase.
 *
 * @package Grasmash\YamlCli\Command
 */
class AcquiaCliApplication extends Application implements LoggerAwareInterface {

  use LoggerAwareTrait;
  use DataStoreAwareTrait;
  use CloudApiDatastoreAwareTrait;

  /**
   * @var null|string*/
  private $repoRoot;

  /**
   * @var \Acquia\Cli\Helpers\LocalMachineHelper
   */
  protected $localMachineHelper;
  /**
   * @var string|null
   */
  private $sshKeysDir;
  /**
   * @var \AcquiaCloudApi\Connector\Client
   */
  private $acquiaCloudClient;

  /**
   * @var string
   */
  protected $acliConfigFilename = 'acquia-cli.json';

  /**
   * @var string
   */
  protected $cloudConfigFilename = 'cloud_api.conf';

  /**
   * @var string
   */
  protected $dataDir;

  /**
   * @return \Acquia\Cli\Helpers\LocalMachineHelper
   */
  public function getLocalMachineHelper(): LocalMachineHelper {
    return $this->localMachineHelper;
  }

  /**
   * Cli constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $repo_root
   *
   * @param string $version
   *
   * @param null $data_dir
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function __construct(
        LoggerInterface $logger,
        InputInterface $input,
        OutputInterface $output,
        $repo_root,
        string $version = 'UNKNOWN',
        $data_dir = NULL
    ) {
    $this->setAutoExit(FALSE);
    $this->setLogger($logger);
    $this->warnIfXdebugLoaded();
    $this->repoRoot = $repo_root;
    $this->setLocalMachineHelper(new LocalMachineHelper($input, $output, $logger));
    parent::__construct('acli', $version);
    $this->dataDir = $data_dir ? $data_dir : $this->getLocalMachineHelper()->getHomeDir() . '/.acquia';
    $this->setDatastore(new JsonFileStore($this->getAcliConfigFilepath()));
    $this->setCloudApiDatastore(new JsonFileStore($this->getCloudConfigFilepath(), JsonFileStore::NO_SERIALIZE_STRINGS));
    $this->initializeAmplitude();

    // Add API commands.
    $api_command_helper = new ApiCommandHelper();
    $this->addCommands($api_command_helper->getApiCommands());

    // Register custom progress bar format.
    ProgressBar::setFormatDefinition(
          'message',
          "%current%/%max% [%bar%] <info>%percent:3s%%</info> -- %elapsed:6s%/%estimated:-6s%\n %message%\n"
      );
  }

  /**
   * Initializes Amplitude.
   */
  private function initializeAmplitude() {
    $amplitude = Amplitude::getInstance();
    $amplitude->init('956516c74386447a3148c2cc36013ac3')
      ->setDeviceId(self::getMachineUuid());
    if (!$this->getDatastore()->get('send_telemetry')) {
      $amplitude->setOptOut(TRUE);
    }
    $amplitude->logQueuedEvents();
  }

  /**
   * Get a unique ID for the current running environment.
   *
   * Should conform to UUIDs generated by sebhildebrandt/systeminformation.
   *
   * @todo open-source this for re-use across Acquia PHP products?
   * @see https://github.com/drupol/phposinfo/issues/3
   *
   * @see https://github.com/sebhildebrandt/systeminformation/blob/master/lib/osinfo.js
   *
   * @return string|null
   *   The machine UUID.
   */
  public static function getMachineUuid() {
    // phpcs:ignore
    switch (OsInfo::family()) {
      case FamilyName::LINUX:
        return shell_exec('( cat /var/lib/dbus/machine-id /etc/machine-id 2> /dev/null || hostname ) | head -n 1 || :');

      case FamilyName::DARWIN:
        $output = shell_exec('ioreg -rd1 -c IOPlatformExpertDevice | grep IOPlatformUUID');
        $parts = explode('=', str_replace('"', '', $output));
        return strtolower(trim($parts[1]));

      case FamilyName::WINDOWS:
        return shell_exec('%windir%\\System32\\reg query "HKEY_LOCAL_MACHINE\\SOFTWARE\\Microsoft\\Cryptography" /v MachineGuid');

      default:
        return NULL;
    }
  }

  /**
   * @param \Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   */
  public function setLocalMachineHelper(
    LocalMachineHelper $localMachineHelper
  ): void {
    $this->localMachineHelper = $localMachineHelper;
  }

  /**
   * Runs the current application.
   *
   * @param \Symfony\Component\Console\Input\InputInterface|null $input
   * @param \Symfony\Component\Console\Output\OutputInterface|null $output
   *
   * @return int 0 if everything went fine, or an error code
   *
   * @throws \Exception When running fails. Bypass this when <a href='psi_element://setCatchExceptions()'>setCatchExceptions()</a>.
   */
  public function run(InputInterface $input = NULL, OutputInterface $output = NULL) {
    $exit_code = parent::run($input, $output);
    $event_properties = $this->getTelemetryUserData();
    $event_properties['exit_code'] = $exit_code;
    $event_properties['arguments'] = $input->getArguments();
    $event_properties['options'] = $input->getOptions();
    Amplitude::getInstance()->queueEvent('Ran command', $event_properties);

    return $exit_code;
  }

  /**
   * Get telemetry user data.
   *
   * @return array
   *   Telemetry user data.
   */
  public function getTelemetryUserData() {
    return [
      'app_version' => $this->getVersion(),
      // phpcs:ignore
      'platform' => OsInfo::family(),
      'os_name' => OsInfo::os(),
      'os_version' => OsInfo::version(),
    ];
  }

  /**
   * @return null|string
   */
  public function getRepoRoot(): ?string {
    return $this->repoRoot;
  }

  /**
   * Warns the user if the xDebug extension is loaded.
   */
  protected function warnIfXdebugLoaded() {
    $xdebug_loaded = extension_loaded('xdebug');
    if ($xdebug_loaded) {
      $this->logger->warning('<comment>The xDebug extension is loaded. This will significantly decrease performance.</comment>');
    }
  }

  /**
   * @return \Psr\Log\LoggerInterface
   */
  public function getLogger(): LoggerInterface {
    return $this->logger;
  }

  /**
   * @param string|null $sshKeysDir
   */
  public function setSshKeysDir(?string $sshKeysDir): void {
    $this->sshKeysDir = $sshKeysDir;
  }

  /**
   * @return string
   */
  public function getSshKeysDir(): string {
    if (!isset($this->sshKeysDir)) {
      $this->sshKeysDir = $this->getLocalMachineHelper()->getLocalFilepath('~/.ssh');
    }

    return $this->sshKeysDir;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $client
   */
  public function setAcquiaCloudClient(Client $client) {
    $this->acquiaCloudClient = $client;
  }

  /**
   * @return \AcquiaCloudApi\Connector\Client
   */
  public function getAcquiaCloudClient(): Client {
    if (isset($this->acquiaCloudClient)) {
      return $this->acquiaCloudClient;
    }

    $cloud_api_conf = $this->getCloudApiDatastore();
    $config = [
      'key' => $cloud_api_conf->get('key'),
      'secret' => $cloud_api_conf->get('secret'),
    ];
    $connector = new Connector($config);
    $this->acquiaCloudClient = Client::factory($connector);

    return $this->acquiaCloudClient;
  }

  /**
   * @return string
   */
  public function getCloudConfigFilename(): string {
    return $this->cloudConfigFilename;
  }

  /**
   * @return string
   */
  public function getAcliConfigFilename(): string {
    return $this->acliConfigFilename;
  }

  public function getCloudConfigFilepath(): string {
    return $this->dataDir . '/' . $this->getCloudConfigFilename();
  }

  public function getAcliConfigFilepath(): string {
    return $this->dataDir . '/' . $this->getAcliConfigFilename();
  }

}
