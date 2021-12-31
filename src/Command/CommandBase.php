<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\DataStore\YamlStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\AliasHelper;
use Acquia\Cli\Helpers\CloudProxyHelper;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Helpers\UpdateHelper;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Response\ApplicationResponse;
use AcquiaLogstream\LogstreamManager;
use Closure;
use Exception;
use loophp\phposinfo\OsInfo;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;
use Webmozart\KeyValueStore\JsonFileStore;
use Zumba\Amplitude\Amplitude;

/**
 * Class CommandBase.
 *
 * @package Grasmash\YamlCli\Command
 */
abstract class CommandBase extends Command implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * @var InputInterface
   */
  protected $input;

  /**
   * @var OutputInterface
   */
  protected $output;

  /**
   * @var SymfonyStyle
   */
  protected $io;

  /**
   * @var \Symfony\Component\Console\Helper\FormatterHelper*/
  protected $formatter;

  /**
   * @var ApplicationResponse
   */
  private $cloudApplication;

  /**
   * @var TelemetryHelper
   */
  protected $telemetryHelper;

  /**
   * @var LocalMachineHelper
   */
  public $localMachineHelper;

  /**
   * @var JsonFileStore
   */
  protected $datastoreCloud;

  /**
   * @var YamlStore
   */
  protected $datastoreAcli;

  /**
   * @var CloudCredentials
   */
  protected $cloudCredentials;

  /**
   * @var string
   */
  protected $cloudConfigFilepath;

  /**
   * @var string
   */
  protected $acliConfigFilepath;

  /**
   * @var string
   */
  protected $repoRoot;

  /**
   * @var ClientService
   */
  protected $cloudApiClientService;

  /**
   * @var LogstreamManager
   */
  protected $logstreamManager;

  /**
   * @var SshHelper
   */
  public $sshHelper;

  /**
   * @var string
   */
  protected $sshDir;

  protected $dir;

  /**
   * @var bool
   */
  protected $drushHasActiveDatabaseConnection;

  /**
   * @var \Acquia\Cli\Helpers\AliasHelper
   */
  private $aliasHelper;

  /**
   * @var \Acquia\Cli\Helpers\CloudProxyHelper
   */
  private CloudProxyHelper $cloudProxyHelper;

  /**
   * @var \Acquia\Cli\Helpers\UpdateHelper
   */
  private UpdateHelper $updateHelper;

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
   * @param ClientService $cloudApiClientService
   * @param LogstreamManager $logstreamManager
   * @param SshHelper $sshHelper
   * @param string $sshDir
   * @param ConsoleLogger $logger
   * @param \Acquia\Cli\Helpers\AliasHelper $alias_helper
   * @param \Acquia\Cli\Helpers\CloudProxyHelper $cloud_proxy_helper
   * @param \Acquia\Cli\Helpers\UpdateHelper $update_helper
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
    LoggerInterface $logger,
    AliasHelper $alias_helper,
    CloudProxyHelper $cloud_proxy_helper,
    UpdateHelper $update_helper
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
    $this->aliasHelper = $alias_helper;
    $this->cloudProxyHelper = $cloud_proxy_helper;
    $this->updateHelper = $update_helper;
    parent::__construct();
  }

  /**
   * @param string $repoRoot
   */
  public function setRepoRoot(string $repoRoot): void {
    $this->repoRoot = $repoRoot;
  }

  /**
   * @return string
   */
  public function getRepoRoot(): string {
    return $this->repoRoot;
  }

  /**
   * Initializes the command just after the input has been validated.
   *
   * @param InputInterface $input
   *   An InputInterface instance.
   * @param OutputInterface $output
   *   An OutputInterface instance.
   *
   * @throws AcquiaCliException
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException|\GuzzleHttp\Exception\GuzzleException
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $this->io = new SymfonyStyle($input, $output);
    // Register custom progress bar format.
    ProgressBar::setFormatDefinition(
      'message',
      "%current%/%max% [%bar%] <info>%percent:3s%%</info> -- %elapsed:6s%/%estimated:-6s%\n %message%"
    );
    $this->formatter = $this->getHelper('formatter');

    $this->output->writeln('Acquia CLI version: ' . $this->getApplication()->getVersion(), OutputInterface::VERBOSITY_DEBUG);
    $this->telemetryHelper->checkAndPromptTelemetryPreference($this);
    $this->cloudApiClientService->migrateLegacyApiKey();
    $this->telemetryHelper->initializeAmplitude();

    if ($this->commandRequiresAuthentication($this->input) && !self::isMachineAuthenticated($this->datastoreCloud)) {
      throw new AcquiaCliException('This machine is not yet authenticated with the Cloud Platform. Please run `acli auth:login`');
    }

    $this->aliasHelper->convertApplicationAliasToUuid($input);
    $this->fillMissingRequiredApplicationUuid($input, $output);
    $this->aliasHelper->convertEnvironmentAliasToUuid($input, 'environmentId');
    $this->aliasHelper->convertEnvironmentAliasToUuid($input, 'source');
    if ($latest = $this->checkForNewVersion()) {
      $this->output->writeln("Acquia CLI {$latest} is available. Run <options=bold>acli self-update</> to update.");
    }
  }

  /**
   * @param JsonFileStore $cloud_datastore
   *
   * @return bool
   */
  public static function isMachineAuthenticated(JsonFileStore $cloud_datastore): bool {
    if (getenv('ACLI_ACCESS_TOKEN')) {
      return TRUE;
    }

    if (getenv('ACLI_KEY') && getenv('ACLI_SECRET') ) {
      return TRUE;
    }

    if ($cloud_datastore === NULL) {
      return FALSE;
    }

    $acli_key = $cloud_datastore->get('acli_key');
    $keys = $cloud_datastore->get('keys');
    if ($acli_key && $keys && array_key_exists($acli_key, $keys)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   *
   * @return int
   * @throws \Exception
   */
  public function run(InputInterface $input, OutputInterface $output) {
    $exit_code = parent::run($input, $output);
    if ($exit_code === 0 && in_array($input->getFirstArgument(), ['self-update', 'update'])) {
      // Exit immediately to avoid loading additional classes breaking updates.
      // @see https://github.com/acquia/cli/issues/218
      return $exit_code;
    }
    $event_properties = [
      'exit_code' => $exit_code,
      'arguments' => $input->getArguments(),
      'options' => $input->getOptions(),
      'app_version' => $this->getApplication()->getVersion(),
      // phpcs:ignore
      'platform' => OsInfo::family(),
      'os_name' => OsInfo::os(),
      'os_version' => OsInfo::version(),
    ];
    Amplitude::getInstance()->queueEvent('Ran command', $event_properties);

    return $exit_code;
  }

  /**
   * Add argument and usage examples for applicationUuid.
   */
  protected function acceptApplicationUuid(): CommandBase {
    $this->addArgument('applicationUuid', InputArgument::OPTIONAL, 'The Cloud Platform application UUID or alias')
      ->addUsage(self::getDefaultName() . ' [<applicationAlias>]')
      ->addUsage(self::getDefaultName() . ' myapp')
      ->addUsage(self::getDefaultName() . ' abcd1234-1111-2222-3333-0e02b2c3d470');

    return $this;
  }

  /**
   * Add argument and usage examples for environmentId.
   */
  protected function acceptEnvironmentId(): CommandBase {
    $this->addArgument('environmentId', InputArgument::OPTIONAL, 'The Cloud Platform environment ID or alias')
      ->addUsage(self::getDefaultName() . ' [<environmentAlias>]')
      ->addUsage(self::getDefaultName() . ' myapp.dev')
      ->addUsage(self::getDefaultName() . ' 12345-abcd1234-1111-2222-3333-0e02b2c3d470');

    return $this;
  }

  /**
   * Add site argument.
   *
   * Only call this after acceptEnvironmentId() to keep arguments in the expected order.
   *
   * @return $this
   */
  protected function acceptSite(): CommandBase {
    // Do not set a default site in order to force a user prompt.
    $this->addArgument('site', InputArgument::OPTIONAL, 'For a multisite application, the directory name of the site')
      ->addUsage(self::getDefaultName() . ' myapp.dev default');

    return $this;
  }

  /**
   * Indicates whether the command requires the machine to be authenticated with the Cloud Platform.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    // Assume commands require authentication unless they opt out by overriding this method.
    return TRUE;
  }

  /**
   * @param string $uuid
   *
   * @return string
   */
  public static function validateUuid(string $uuid): string {
    $violations = Validation::createValidator()->validate($uuid, [
      new Length([
        'value' => 36,
      ]),
      new Regex([
        'pattern' => '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i',
        'message' => 'This is not a valid UUID.',
      ]),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $uuid;
  }

  /**
   * @throws AcquiaCliException
   */
  protected function validateCwdIsValidDrupalProject(): void {
    if (!$this->repoRoot) {
      throw new AcquiaCliException('Could not find a local Drupal project. Looked for `docroot/index.php` in current and parent directories. Please execute this command from within a Drupal project directory.');
    }
  }

  /**
   * @param string $command_name
   * @param array $arguments
   *
   * @return int
   * @throws \Exception
   */
  protected function executeAcliCommand(string $command_name, array $arguments = []): int {
    $command = $this->getApplication()->find($command_name);
    array_unshift($arguments, ['command' => $command_name]);
    $create_input = new ArrayInput($arguments);

    return $command->run($create_input, new NullOutput());
  }

  /**
   * @return bool|string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function checkForNewVersion() {
    // Input not set if called from an exception listener.
    if (!isset($this->input)) {
      return FALSE;
    }
    // Running on API commands would corrupt JSON output.
    if (strpos($this->input->getArgument('command'), 'api:') !== FALSE) {
      return FALSE;
    }
    // Bail for development builds.
    if ($this->getApplication()->getVersion() === '@package_version@') {
      return FALSE;
    }
    // Bail in Cloud IDEs to avoid hitting Github API rate limits.
    if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      return FALSE;
    }
    try {
      if ($latest = $this->updateHelper->hasUpdate()) {
        return $latest;
      }
    } catch (Exception $e) {
      $this->logger->debug("Could not determine if Acquia CLI has a new version available.");
    }
    return FALSE;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function setDirAndRequireProjectCwd(InputInterface $input): void {
    $this->determineDir($input);
    if ($this->dir !== '/home/ide/project' && AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      throw new AcquiaCliException('Please run this command from the {dir} directory', ['dir' => '/home/ide/project']);
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   */
  protected function determineDir(InputInterface $input): void {
    if (isset($this->dir)) {
      return;
    }

    if ($input->hasOption('dir') && $dir = $input->getOption('dir')) {
      $this->dir = $dir;
    }
    elseif ($this->repoRoot) {
      $this->dir = $this->repoRoot;
    }
    else {
      $this->dir = getcwd();
    }
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Acquia\Cli\Output\Checklist $checklist
   *
   * @return \Closure
   */
  protected function getOutputCallback(OutputInterface $output, Checklist $checklist): Closure {
    return static function ($type, $buffer) use ($checklist, $output) {
      if (!$output->isVerbose() && $checklist->getItems()) {
        $checklist->updateProgressBar($buffer);
      }
      $output->writeln($buffer, OutputInterface::VERBOSITY_VERY_VERBOSE);
    };
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   *
   * @throws \Exception
   */
  public function fillMissingRequiredApplicationUuid(InputInterface $input, OutputInterface $output): void {
    if ($input->hasArgument('applicationUuid') && !$input->getArgument('applicationUuid') && $this->getDefinition()->getArgument('applicationUuid')->isRequired()) {
      $output->writeln('Inferring Cloud Application UUID for this command since none was provided...', OutputInterface::VERBOSITY_VERBOSE);
      if ($application_uuid = $this->cloudProxyHelper->determineCloudApplication()) {
        $output->writeln("Set application uuid to <options=bold>$application_uuid</>", OutputInterface::VERBOSITY_VERBOSE);
        $input->setArgument('applicationUuid', $application_uuid);
      }
    }
  }

}
