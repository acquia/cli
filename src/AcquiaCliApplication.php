<?php

namespace Acquia\Cli;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\ClientService;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaLogstream\LogstreamManager;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Webmozart\KeyValueStore\JsonFileStore;
use Zumba\Amplitude\Amplitude;

/**
 * Class CommandBase.
 *
 * @package Grasmash\YamlCli\Command
 */
class AcquiaCliApplication extends Application implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * @var \Symfony\Component\DependencyInjection\Container
   */
  private $container;

  /**
   * @var string|null
   */
  private $sshKeysDir;

  /**
   * @var \Acquia\Cli\Helpers\SshHelper
   */
  protected $sshHelper;

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
   * @throws \Exception
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

    // Add API commands.
    $api_command_helper = new ApiCommandHelper();
    $this->addCommands($api_command_helper->getApiCommands());

    // Register custom progress bar format.
    ProgressBar::setFormatDefinition(
          'message',
          "%current%/%max% [%bar%] <info>%percent:3s%%</info> -- %elapsed:6s%/%estimated:-6s%\n %message%\n"
      );

    // Create custom <code> output format.
    $outputStyle = new OutputFormatterStyle('cyan', NULL);
    $output->getFormatter()->setStyle('code', $outputStyle);

    // Clean up exceptions thrown during commands.
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ConsoleEvents::ERROR, function (ConsoleErrorEvent $event) {
      $exitCode = $event->getExitCode();
      $error = $event->getError();
      // Make OAuth server errors more human-friendly.
      if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
        $event->setError(new AcquiaCliException('Your Cloud API credentials are invalid. Run acli auth:login to reset them.', [], $exitCode));
      }
    });
    $this->setDispatcher($dispatcher);
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Psr\Log\LoggerInterface $logger
   *
   * @throws \Exception
   */
  public static function configureContainer(ContainerBuilder $container, InputInterface $input, OutputInterface $output, LoggerInterface $logger) {
    $container->set('amplitude', Amplitude::getInstance());
    $container->register('local_machine_helper', LocalMachineHelper::class)
      ->addArgument($input)
      ->addArgument($output)
      ->addArgument($logger);

    $container->setParameter('cloud_config.filename', 'cloud_api.conf');
    $container->setParameter('acli_config.filename', 'acquia-cli.json');
    $container->setParameter('cloud_config.filepath', $container->getParameter('data_dir') . '/' . $container->getParameter('cloud_config.filename'));
    $container->setParameter('acli_config.filepath', $container->getParameter('data_dir') . '/' . $container->getParameter('acli_config.filename'));

    $container->register('acli_datastore', JsonFileStore::class)
      ->addArgument($container->getParameter('acli_config.filepath'));

    $container->register('cloud_datastore', JsonFileStore::class)
      ->addArgument($container->getParameter('cloud_config.filepath'))
      ->addArgument(JsonFileStore::NO_SERIALIZE_STRINGS);

    $container->register('cloud_api', ClientService::class)
      ->addArgument(new Reference('cloud_datastore'));

    $container->register('telemetry_helper', TelemetryHelper::class)
      ->addArgument($input)
      ->addArgument($output)
      ->addArgument(new Reference('cloud_api'))
      ->addArgument(new Reference('acli_datastore'))
      ->addArgument(new Reference('cloud_datastore'));

    $container->register('logstream_manager', LogstreamManager::class)
      ->addArgument($input)
      ->addArgument($output);
  }

  /**
   * @param \Acquia\Cli\Helpers\SshHelper $sshHelper
   * Initializes Amplitude.
   *
   * @throws \Exception
   */
  public function setSshHelper(SshHelper $sshHelper): void {
    $this->sshHelper = $sshHelper;
  }

  public function getContainer() {
    return $this->container;
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
   * @throws \Exception
   */
  public function getSshKeysDir(): string {
    if (!isset($this->sshKeysDir)) {
      $this->sshKeysDir = $this->getContainer()->get('local_machine_helper')->getLocalFilepath('~/.ssh');
    }

    return $this->sshKeysDir;
  }

  /**
   * @param \Webmozart\KeyValueStore\JsonFileStore $cloud_datastore
   *
   * @return bool
   * @throws \Exception
   */
  public static function isMachineAuthenticated(JsonFileStore $cloud_datastore): bool {
    return $cloud_datastore !== NULL && $cloud_datastore->get('key') && $cloud_datastore->get('secret');
  }

}
