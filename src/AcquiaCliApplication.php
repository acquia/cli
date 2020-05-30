<?php

namespace Acquia\Cli;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\ClientService;
use Acquia\Cli\Helpers\CloudApiDataStoreAwareTrait;
use Acquia\Cli\Helpers\DataStoreAwareTrait;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaLogstream\LogstreamManager;
use drupol\phposinfo\OsInfo;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * Class CommandBase.
 *
 * @package Grasmash\YamlCli\Command
 */
class AcquiaCliApplication extends Application implements LoggerAwareInterface {

  use LoggerAwareTrait;
  use DataStoreAwareTrait;
  use CloudApiDatastoreAwareTrait;

  private $container;

  /**
   * @var string|null
   */
  private $sshKeysDir;

  /**
   * @var \Acquia\Cli\Helpers\SshHelper
   */
  protected $sshHelper;

  public $logStreamManager;

  /**
   * @return \Acquia\Cli\Helpers\SshHelper
   */
  public function getSshHelper(): SshHelper {
    return $this->sshHelper;
  }

  /**
   * Cli constructor.
   *
   * @param \Symfony\Component\DependencyInjection\Container $container
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @param string $version
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function __construct(
    Container $container,
    LoggerInterface $logger,
    InputInterface $input,
    OutputInterface $output,
    string $version = 'UNKNOWN'
  ) {
    $this->container = $container;
    $this->setAutoExit(FALSE);
    $this->setLogger($logger);
    $this->warnIfXdebugLoaded();
    $this->setSshHelper(new SshHelper($this, $output));
    parent::__construct('acli', $version);
    $this->setDatastore(new JsonFileStore($this->getContainer()->getParameter('acli_config.filepath')));
    $this->setCloudApiDatastore(new JsonFileStore($this->getContainer()->getParameter('cloud_config.filepath'), JsonFileStore::NO_SERIALIZE_STRINGS));
    $definition = $container->register('cloud_api', ClientService::class);
    $definition->setArgument(0, $this->getCloudApiDatastore());
    $this->logStreamManager = new LogstreamManager($input, $output);

    $this->initializeAmplitude();

    // Add API commands.
    $api_command_helper = new ApiCommandHelper();
    $this->addCommands($api_command_helper->getApiCommands());

    // Register custom progress bar format.
    ProgressBar::setFormatDefinition(
          'message',
          "%current%/%max% [%bar%] <info>%percent:3s%%</info> -- %elapsed:6s%/%estimated:-6s%\n %message%\n"
      );

    // Clean up exceptions thrown during commands.
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ConsoleEvents::ERROR, function (ConsoleErrorEvent $event) {
      $exitCode = $event->getExitCode();
      $error = $event->getError();
      // Make OAuth server errors more human-friendly.
      if ($error instanceof IdentityProviderException && $error->getMessage() == 'invalid_client') {
        $event->setError(new AcquiaCliException('Your Cloud API credentials are invalid. Run acli auth:login to reset them.', [], $exitCode));
      }
    });
    $this->setDispatcher($dispatcher);
  }

  /**
   * @param \Acquia\Cli\Helpers\SshHelper $sshHelper
   */
  public function setSshHelper(SshHelper $sshHelper): void {
    $this->sshHelper = $sshHelper;
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
    $event_properties = [
      'exit_code' => $exit_code,
      'arguments' => $input->getArguments(),
      'options' => $input->getOptions(),
    ];
    $amplitude = $this->getContainer()->get('amplitude');
    $amplitude->queueEvent('Ran command', $event_properties);

    return $exit_code;
  }

  /**
   * Initializes Amplitude.
   */
  private function initializeAmplitude() {
    $amplitude = $this->getContainer()->get('amplitude');
    $amplitude->init('956516c74386447a3148c2cc36013ac3');
    // Method chaining breaks Prophecy?
    // @see https://github.com/phpspec/prophecy/issues/25
    $amplitude->setDeviceId(OsInfo::uuid());
    $amplitude->setUserProperties($this->getTelemetryUserData());
    try {
      $amplitude->setUserId($this->getUserId());
    } catch (IdentityProviderException $e) {
      // If something is wrong with the Cloud API client, don't bother users.
    }
    if (!$this->getDatastore()->get(DataStoreContract::SEND_TELEMETRY)) {
      $amplitude->setOptOut(TRUE);
    }
    $amplitude->logQueuedEvents();
  }

  public function getContainer() {
    return $this->container;
  }

  /**
   * Get telemetry user data.
   *
   * @return array
   *   Telemetry user data.
   */
  public function getTelemetryUserData() {
    $data = [
      'app_version' => $this->getVersion(),
      // phpcs:ignore
      'platform' => OsInfo::family(),
      'os_name' => OsInfo::os(),
      'os_version' => OsInfo::version(),
      'ah_env' => AcquiaDrupalEnvironmentDetector::getAhEnv(),
      'ah_group' => AcquiaDrupalEnvironmentDetector::getAhGroup(),
      'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
    ];
    try {
      $user = $this->getUserData();
      if (isset($user['is_acquian'])) {
        $data['is_acquian'] = $user['is_acquian'];
      }
    }
    catch (IdentityProviderException $e) {
      // If something is wrong with the Cloud API client, don't bother users.
    }
    return $data;
  }

  /**
   * Get user uuid.
   *
   * @return string|null
   *   User UUID from Cloud.
   */
  public function getUserId() {
    $user = $this->getUserData();
    if ($user && isset($user['uuid'])) {
      return $user['uuid'];
    }
    else {
      return NULL;
    }
  }

  /**
   * Get user data.
   *
   * @return array|null
   *   User account data from Cloud.
   */
  public function getUserData() {
    $datastore = $this->getDatastore();
    $user = $datastore->get(DataStoreContract::USER);

    if (!$user && $this->isMachineAuthenticated()) {
      $client = $this->getContainer()->get('cloud_api')->getClient();
      $account = new Account($client);
      $user_account = $account->get();
      $user = [
        'uuid' => $user_account->uuid,
        'is_acquian' => substr($user_account->mail, -10, 10) === 'acquia.com'
      ];
      $datastore->set(DataStoreContract::USER, $user);
    }

    return $user;
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
      $this->sshKeysDir = $this->getContainer()->get('local_machine_helper')->getLocalFilepath('~/.ssh');
    }

    return $this->sshKeysDir;
  }

  /**
   * @return bool
   */
  public function isMachineAuthenticated(): bool {
    $cloud_api_conf = $this->getCloudApiDatastore();
    return $cloud_api_conf !== NULL && $cloud_api_conf->get('key') && $cloud_api_conf->get('secret');
  }

}
