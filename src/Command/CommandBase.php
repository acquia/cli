<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\DataStore\YamlStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Endpoints\Logs;
use AcquiaCloudApi\Response\ApplicationResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaLogstream\LogstreamManager;
use Closure;
use Composer\Semver\VersionParser;
use Exception;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use loophp\phposinfo\OsInfo;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\ItemInterface;
use Webmozart\KeyValueStore\JsonFileStore;
use Webmozart\KeyValueStore\Util\Serializer;
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

  protected $localDbUser;
  protected $localDbPassword;
  protected $localDbName;
  protected $localDbHost;

  /**
   * @var bool
   */
  protected $drushHasActiveDatabaseConnection;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $updateClient;

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

  protected function setLocalDbUser(): void {
    $this->localDbUser = 'drupal';
    if ($lando_info = self::getLandoInfo()) {
      $this->localDbUser = $lando_info->database->creds->user;
    }
    if (getenv('ACLI_DB_USER')) {
      $this->localDbUser = getenv('ACLI_DB_USER');
    }
  }

  public function getDefaultLocalDbUser() {
    if (!isset($this->localDbUser)) {
      $this->setLocalDbUser();
    }

    return $this->localDbUser;
  }

  protected function setLocalDbPassword(): void {
    $this->localDbPassword = 'drupal';
    if ($lando_info = self::getLandoInfo()) {
      $this->localDbPassword = $lando_info->database->creds->password;
    }
    if (getenv('ACLI_DB_PASSWORD')) {
      $this->localDbPassword = getenv('ACLI_DB_PASSWORD');
    }
  }

  /**
   * @return mixed
   */
  public function getDefaultLocalDbPassword() {
    if (!isset($this->localDbPassword)) {
      $this->setLocalDbPassword();
    }

    return $this->localDbPassword;
  }

  protected function setLocalDbName(): void {
    $this->localDbName = 'drupal';
    if ($lando_info = self::getLandoInfo()) {
      $this->localDbName = $lando_info->database->creds->database;
    }
    if (getenv('ACLI_DB_NAME')) {
      $this->localDbName = getenv('ACLI_DB_NAME');
    }
  }

  /**
   * @return mixed
   */
  public function getDefaultLocalDbName() {
    if (!isset($this->localDbName)) {
      $this->setLocalDbName();
    }

    return $this->localDbName;
  }

  protected function setLocalDbHost(): void {
    $this->localDbHost = 'localhost';
    if ($lando_info = self::getLandoInfo()) {
      $this->localDbHost = $lando_info->database->hostnames[0];
    }
    if (getenv('ACLI_DB_HOST')) {
      $this->localDbHost = getenv('ACLI_DB_HOST');
    }
  }

  /**
   * @return mixed
   */
  public function getDefaultLocalDbHost() {
    if (!isset($this->localDbHost)) {
      $this->setLocalDbHost();
    }

    return $this->localDbHost;
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
   * @throws \Psr\Cache\InvalidArgumentException
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
    $this->checkAndPromptTelemetryPreference();
    $this->migrateLegacyApiKey();
    $this->telemetryHelper->initializeAmplitude();

    if ($this->commandRequiresAuthentication($this->input) && !self::isMachineAuthenticated($this->datastoreCloud, $this->input)) {
      throw new AcquiaCliException('This machine is not yet authenticated with the Cloud Platform. Please run `acli auth:login`');
    }

    $this->convertApplicationAliasToUuid($input);
    $this->fillMissingRequiredApplicationUuid($input, $output);
    $this->convertEnvironmentAliasToUuid($input, 'environmentId');
    $this->convertEnvironmentAliasToUuid($input, 'source');
    if ($latest = $this->checkForNewVersion()) {
      $this->output->writeln("Acquia CLI {$latest} is available. Run <options=bold>acli self-update</> to update.");
    }
  }

  /**
   * @param JsonFileStore $cloud_datastore
   *
   * @return bool
   */
  public static function isMachineAuthenticated(JsonFileStore $cloud_datastore, InputInterface $input): bool {
    if ($input->hasOption('key') && $input->getOption('key') && $input->hasOption('secret') && $input->getOption('secret')) {
      return TRUE;
    }
    if (getenv('ACLI_ACCESS_TOKEN')) {
      return TRUE;
    }

    if (getenv('ACLI_KEY') && getenv('ACLI_SECRET') ) {
      return TRUE;
    }

    $acli_key = $cloud_datastore->get('acli_key');
    $keys = $cloud_datastore->get('keys');
    if ($acli_key && $keys && array_key_exists($acli_key, $keys)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if telemetry preference is set, prompt if not.
   */
  public function checkAndPromptTelemetryPreference(): void {
    $send_telemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
    if (!isset($send_telemetry) || is_null($send_telemetry)) {
      $this->migrateLegacySendTelemetryPreference();
      $send_telemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
    }
    // Convert from serialized to unserialized.
    if ($this->datastoreCloud
      && $this->datastoreCloud->get('user')
      && is_string($this->datastoreCloud->get('user'))
      && strpos($this->datastoreCloud->get('user'), 'a:') === 0
    ) {
      $value = Serializer::unserialize($this->datastoreCloud->get('user'));
      $this->datastoreCloud->set('user', $value);
    }
    if ($this->getName() !== 'telemetry' && (!isset($send_telemetry) || is_null($send_telemetry)) && $this->input->isInteractive()) {
      $this->output->writeln('We strive to give you the best tools for development.');
      $this->output->writeln('You can really help us improve by sharing anonymous performance and usage data.');
      $style = new SymfonyStyle($this->input, $this->output);
      $pref = $style->confirm('Would you like to share anonymous performance usage and data?');
      $this->datastoreCloud->set(DataStoreContract::SEND_TELEMETRY, $pref);
      if ($pref) {
        $this->output->writeln('Awesome! Thank you for helping!');
      }
      else {
        // @todo Completely anonymously send an event to indicate some user opted out.
        $this->output->writeln('Ok, no data will be collected and shared with us.');
        $this->output->writeln('We take privacy seriously.');
        $this->output->writeln('If you change your mind, run <options=bold>acli telemetry</>.');
      }
    }
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
  protected function acceptApplicationUuid() {
    $this->addArgument('applicationUuid', InputArgument::OPTIONAL, 'The Cloud Platform application UUID or alias')
      ->addUsage(self::getDefaultName() . ' [<applicationAlias>]')
      ->addUsage(self::getDefaultName() . ' myapp')
      ->addUsage(self::getDefaultName() . ' abcd1234-1111-2222-3333-0e02b2c3d470');
  }

  /**
   * Add argument and usage examples for environmentId.
   */
  protected function acceptEnvironmentId() {
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
  protected function acceptSite() {
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
   * Prompts the user to choose from a list of available Cloud Platform
   * applications.
   *
   * @param Client $acquia_cloud_client
   *
   * @return null|object|array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function promptChooseApplication(
    Client $acquia_cloud_client
  ) {
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();

    if (!$customer_applications->count()) {
      throw new AcquiaCliException("You have no Cloud applications.");
    }
    return $this->promptChooseFromObjectsOrArrays(
      $customer_applications,
      'uuid',
      'name',
      'Please select a Cloud Platform application:'
    );
  }

  /**
   * Prompts the user to choose from a list of environments for a given Cloud Platform application.
   *
   * @param Client $acquia_cloud_client
   * @param string $application_uuid
   *
   * @return null|object|array
   */
  protected function promptChooseEnvironment(
    Client $acquia_cloud_client,
    string $application_uuid
  ) {
    $environment_resource = new Environments($acquia_cloud_client);
    $environments = $environment_resource->getAll($application_uuid);
    // @todo Make sure there are actually environments here.
    return $this->promptChooseFromObjectsOrArrays(
      $environments,
      'uuid',
      'name',
      'Please select a Cloud Platform environment:'
    );
  }

  /**
   * Prompts the user to choose from a list of logs for a given Cloud Platform environment.
   *
   * @param Client $acquia_cloud_client
   * @param string $environment_id
   *
   * @return null|object|array
   */
  protected function promptChooseLogs(
    Client $acquia_cloud_client,
    string $environment_id
  ) {
    $logs_resource = new Logs($acquia_cloud_client);
    $logs = $logs_resource->getAll($environment_id);

    return $this->promptChooseFromObjectsOrArrays(
      $logs,
      'type',
      'label',
      'Please select one or more logs as a comma-separated list:',
      TRUE
    );
  }

  /**
   * Prompt a user to choose from a list.
   *
   * The list is generated from an array of objects. The objects much have at least one unique property and one
   * property that can be used as a human readable label.
   *
   * @param object[]|array[] $items An array of objects or arrays.
   * @param string $unique_property The property of the $item that will be used to identify the object.
   * @param string $label_property
   * @param string $question_text
   *
   * @param bool $multiselect
   *
   * @return null|array|object
   */
  public function promptChooseFromObjectsOrArrays($items, string $unique_property, string $label_property, string $question_text, $multiselect = FALSE) {
    $list = [];
    foreach ($items as $item) {
      if (is_array($item)) {
        $list[$item[$unique_property]] = trim($item[$label_property]);
      }
      else {
        $list[$item->$unique_property] = trim($item->$label_property);
      }
    }
    $labels = array_values($list);
    $default = $multiselect ? NULL : $labels[0];
    $question = new ChoiceQuestion($question_text, $labels, $default);
    $question->setMultiselect($multiselect);
    $choice_id = $this->io->askQuestion($question);
    if (!$multiselect) {
      $identifier = array_search($choice_id, $list, TRUE);
      foreach ($items as $item) {
        if (is_array($item)) {
          if ($item[$unique_property] === $identifier) {
            return $item;
          }
        }
        else {
          if ($item->$unique_property === $identifier) {
            return $item;
          }
        }
      }
    }
    else {
      $chosen = [];
      foreach ($choice_id as $choice) {
        $identifier = array_search($choice, $list, TRUE);
        foreach ($items as $item) {
          if (is_array($item)) {
            if ($item[$unique_property] === $identifier) {
              $chosen[] = $item;
            }
          }
          else {
            if ($item->$unique_property === $identifier) {
              $chosen[] = $item;
            }
          }
        }
      }
      return $chosen;
    }

    return NULL;
  }

  /**
   * Load configuration from .git/config.
   *
   * @return array|null
   */
  protected function getGitConfig(): ?array {
    $file_path = $this->repoRoot . '/.git/config';
    if (file_exists($file_path)) {
      return parse_ini_file($file_path, TRUE);
    }

    return NULL;
  }

  /**
   * Gets an array of git remotes from a .git/config array.
   *
   * @param array $git_config
   *
   * @return array
   *   A flat array of git remote urls.
   */
  protected function getGitRemotes(array $git_config): array {
    $local_vcs_remotes = [];
    foreach ($git_config as $section_name => $section) {
      if ((strpos($section_name, 'remote ') !== FALSE) &&
        (strpos($section['url'], 'acquia.com') || strpos($section['url'], 'acquia-sites.com'))
      ) {
        $local_vcs_remotes[] = $section['url'];
      }
    }

    return $local_vcs_remotes;
  }

  /**
   * @param Client $acquia_cloud_client
   * @param array $local_git_remotes
   *
   * @return ApplicationResponse|null
   */
  protected function findCloudApplicationByGitUrl(
        Client $acquia_cloud_client,
        array $local_git_remotes
    ): ?ApplicationResponse {

    // Set up API resources.
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();
    $environments_resource = new Environments($acquia_cloud_client);

    // Create progress bar.
    $count = count($customer_applications);
    $progressBar = new ProgressBar($this->output, $count);
    $progressBar->setFormat('message');
    $progressBar->setMessage("Searching <options=bold>$count applications</> on the Cloud Platform...");
    $progressBar->start();

    // Search Cloud applications.
    $terminal_width = (new Terminal())->getWidth();
    foreach ($customer_applications as $application) {
      // Ensure that the message takes up the full terminal width to prevent display artifacts.
      $message = "Searching <options=bold>{$application->name}</> for matching git URLs";
      $suffix_length = $terminal_width - strlen($message) - 17;
      $suffix = $suffix_length > 0 ? str_repeat(' ', $suffix_length) : '';
      $progressBar->setMessage($message . $suffix);
      $application_environments = $environments_resource->getAll($application->uuid);
      if ($application = $this->searchApplicationEnvironmentsForGitUrl(
            $application,
            $application_environments,
            $local_git_remotes
        )) {
        $progressBar->finish();
        $progressBar->clear();

        return $application;
      }
      $progressBar->advance();
    }
    $progressBar->finish();
    $progressBar->clear();

    return NULL;
  }

  /**
   * @param ApplicationResponse $application
   * @param \AcquiaCloudApi\Response\EnvironmentsResponse $application_environments
   * @param array $local_git_remotes
   *
   * @return ApplicationResponse|null
   */
  protected function searchApplicationEnvironmentsForGitUrl(
        $application,
        $application_environments,
        $local_git_remotes
    ): ?ApplicationResponse {
    foreach ($application_environments as $environment) {
      if ($environment->flags->production && in_array($environment->vcs->url, $local_git_remotes, TRUE)) {
        $this->logger->debug("Found matching Cloud application! {$application->name} with uuid {$application->uuid} matches local git URL {$environment->vcs->url}");

        return $application;
      }
    }

    return NULL;
  }

  /**
   * Infer which Cloud Platform application is associated with the current local git repository.
   *
   * If the local git repository has a remote with a URL that matches a Cloud Platform application's VCS URL, assume
   * that we have a match.
   *
   * @param Client $acquia_cloud_client
   *
   * @return ApplicationResponse|null
   */
  protected function inferCloudAppFromLocalGitConfig(
    Client $acquia_cloud_client
    ): ?ApplicationResponse {
    if ($this->repoRoot && $this->input->isInteractive()) {
      $this->output->writeln("There is no Cloud Platform application linked to <options=bold>{$this->repoRoot}/.git</>.");
      $answer = $this->io->confirm('Would you like Acquia CLI to search for a Cloud application that matches your local git config?');
      if ($answer) {
        $this->output->writeln('Searching for a matching Cloud application...');
        if ($git_config = $this->getGitConfig()) {
          $local_git_remotes = $this->getGitRemotes($git_config);
          if ($cloud_application = $this->findCloudApplicationByGitUrl($acquia_cloud_client,
            $local_git_remotes)) {
            $this->output->writeln('<info>Found a matching application!</info>');
            return $cloud_application;
          }
          else {
            $this->output->writeln('<comment>Could not find a matching Cloud application.</comment>');
            return NULL;
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Determine the Cloud environment.
   *
   * @return mixed
   * @throws \Exception
   */
  protected function determineCloudEnvironment() {
    if ($this->input->hasArgument('environmentId') && $this->input->getArgument('environmentId')) {
      return $this->input->getArgument('environmentId');
    }

    if (!$this->input->isInteractive()) {
      throw new RuntimeException('Not enough arguments (missing: "environmentId").');
    }

    $application_uuid = $this->determineCloudApplication();
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environment = $this->promptChooseEnvironment($acquia_cloud_client, $application_uuid);

    return $environment->uuid;
  }

  /**
   * Determine the Cloud application.
   *
   * @param bool $prompt_link_app
   *
   * @return string|null
   * @throws \Exception
   */
  protected function determineCloudApplication($prompt_link_app = FALSE): ?string {
    $application_uuid = $this->doDetermineCloudApplication();
    if (!isset($application_uuid)) {
      throw new AcquiaCliException("Could not determine Cloud Application. Run this command interactively or use `acli link` to link a Cloud Application before running non-interactively.");
    }

    $application = $this->getCloudApplication($application_uuid);
    // No point in trying to link a directory that's not a repo.
    if (!empty($this->repoRoot) && !$this->getCloudUuidFromDatastore()) {
      if ($prompt_link_app) {
        $this->saveCloudUuidToDatastore($application);
      }
      elseif (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !$this->getCloudApplicationUuidFromBltYaml()) {
        $this->promptLinkApplication($application);
      }
    }

    return $application_uuid;
  }

  /**
   * @return array|false|mixed|string|null
   * @throws \Exception
   */
  protected function doDetermineCloudApplication() {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();

    if ($this->input->hasArgument('applicationUuid') && $this->input->getArgument('applicationUuid')) {
      $cloud_application_uuid = $this->input->getArgument('applicationUuid');
      return self::validateUuid($cloud_application_uuid);
    }

    // Try local project info.
    if ($application_uuid = $this->getCloudUuidFromDatastore()) {
      $this->logger->debug("Using Cloud application UUID: $application_uuid from .acquia-cli.yml");
      return $application_uuid;
    }

    if ($application_uuid = $this->getCloudApplicationUuidFromBltYaml()) {
      $this->logger->debug("Using Cloud application UUID $application_uuid from blt/blt.yml");
      return $application_uuid;
    }

    // Get from the Cloud Platform env var.
    if ($application_uuid = self::getThisCloudIdeCloudAppUuid()) {
      return $application_uuid;
    }

    // Try to guess based on local git url config.
    if ($cloud_application = $this->inferCloudAppFromLocalGitConfig($acquia_cloud_client)) {
      return $cloud_application->uuid;
    }

    // Finally, just ask.
    if ($this->input->isInteractive() && $application = $this->promptChooseApplication($acquia_cloud_client)) {
      return $application->uuid;
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  protected function getCloudApplicationUuidFromBltYaml() {
    $blt_yaml_file_path = Path::join($this->repoRoot, 'blt', 'blt.yml');
    if (file_exists($blt_yaml_file_path)) {
      $contents = Yaml::parseFile($blt_yaml_file_path);
      if (array_key_exists('cloud', $contents) && array_key_exists('appId', $contents['cloud'])) {
        return $contents['cloud']['appId'];
      }
    }

    return NULL;
  }

  /**
   * @param string $uuid
   *
   * @return string
   */
  public static function validateUuid($uuid) {
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
   * @param ApplicationResponse $application
   *
   * @return bool
   * @throws \Exception
   */
  protected function saveCloudUuidToDatastore(ApplicationResponse $application): bool {
    $this->datastoreAcli->set('cloud_app_uuid', $application->uuid);
    $this->io->success("The Cloud application {$application->name} has been linked to this repository by writing to .acquia-cli.yml in the repository root.");

    return TRUE;
  }

  /**
   * @return mixed
   */
  protected function getCloudUuidFromDatastore() {
    return $this->datastoreAcli->get('cloud_app_uuid');
  }

  /**
   * @param ApplicationResponse|null $cloud_application
   *
   * @return bool
   * @throws \Exception
   */
  protected function promptLinkApplication(
    ?ApplicationResponse $cloud_application
    ): bool {
    $answer = $this->io->confirm("Would you like to link the Cloud application <bg=cyan;options=bold>{$cloud_application->name}</> to this repository?");
    if ($answer) {
      return $this->saveCloudUuidToDatastore($cloud_application);
    }
    return FALSE;
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
   * Determines if Acquia CLI is being run from within a Cloud IDE.
   *
   * @return bool
   *   TRUE if Acquia CLI is being run from within a Cloud IDE.
   */
  public static function isAcquiaCloudIde(): bool {
    return AcquiaDrupalEnvironmentDetector::isAhIdeEnv();
  }

  /**
   * Get the Cloud Application UUID from a Cloud IDE's environmental variable.
   *
   * This command assumes it is being run inside of a Cloud IDE.
   *
   * @return array|false|string
   */
  protected static function getThisCloudIdeCloudAppUuid() {
    return getenv('ACQUIA_APPLICATION_UUID');
  }

  /**
   * Get the UUID from a Cloud IDE's environmental variable.
   *
   * This command assumes it is being run inside a Cloud IDE.
   *
   * @return false|string
   */
  public static function getThisCloudIdeUuid() {
    return getenv('REMOTEIDE_UUID');
  }

  /**
   * @param string $application_uuid
   *
   * @return ApplicationResponse
   */
  protected function getCloudApplication(string $application_uuid): ApplicationResponse {
    $applications_resource = new Applications($this->cloudApiClientService->getClient());
    return $applications_resource->get($application_uuid);
  }

  /**
   * @param string $environment_id
   *
   * @return EnvironmentResponse
   * @throws \Exception
   */
  protected function getCloudEnvironment(string $environment_id): EnvironmentResponse {
    $environment_resource = new Environments($this->cloudApiClientService->getClient());

    return $environment_resource->get($environment_id);
  }

  /**
   * @param string $command_name
   * @param array $arguments
   *
   * @return int
   * @throws \Exception
   */
  protected function executeAcliCommand($command_name, $arguments = []): int {
    $command = $this->getApplication()->find($command_name);
    array_unshift($arguments, ['command' => $command_name]);
    $create_input = new ArrayInput($arguments);

    return $command->run($create_input, new NullOutput());
  }

  /**
   * @param string $alias
   *
   * @return string
   */
  public static function validateEnvironmentAlias($alias): string {
    $violations = Validation::createValidator()->validate($alias, [
      new Length(['min' => 5]),
      new NotBlank(),
      new Regex(['pattern' => '/.+\..+/', 'message' => 'Environment alias must match the pattern [app-name].[env]']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $alias;
  }

  /**
   * @param string $alias
   *
   * @return string
   */
  protected function normalizeAlias($alias): string {
    return str_replace('@', '', $alias);
  }

  /**
   * @param string $alias
   *
   * @return EnvironmentResponse
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getEnvironmentFromAliasArg($alias): EnvironmentResponse {
    return $this->getEnvFromAlias($alias);
  }

  /**
   * @param $alias
   *
   * @return EnvironmentResponse
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getEnvFromAlias($alias): EnvironmentResponse {
    $cache = self::getAliasCache();
    $value = $cache->get($alias, function (ItemInterface $item) use ($alias) {
      return $this->doGetEnvFromAlias($alias);
    });
    return $value;
  }

  /**
   * @param $alias
   *
   * @return EnvironmentResponse
   * @throws AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function doGetEnvFromAlias($alias): EnvironmentResponse {
    $site_env_parts = explode('.', $alias);
    [$application_alias, $environment_alias] = $site_env_parts;
    $this->logger->debug("Searching for an environment matching alias $application_alias.$environment_alias.");
    $customer_application = $this->getApplicationFromAlias($application_alias);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $acquia_cloud_client->clearQuery();
    $environments_resource = new Environments($acquia_cloud_client);
    $environments = $environments_resource->getAll($customer_application->uuid);
    foreach ($environments as $environment) {
      if ($environment->name === $environment_alias) {
        $this->logger->debug("Found environment {$environment->uuid} matching $environment_alias.");

        return $environment;
      }
    }

    throw new AcquiaCliException("Environment not found matching the alias {alias}", ['alias' => "$application_alias.$environment_alias"]);
  }

  /**
   * @param string $application_alias
   *
   * @return mixed
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getApplicationFromAlias($application_alias) {
    $cache = self::getAliasCache();
    return $cache->get($application_alias, function (ItemInterface $item) use ($application_alias) {
      return $this->doGetApplicationFromAlias($application_alias);
    });
  }

  /**
   * Return the ACLI alias cache.
   * @return FilesystemAdapter
   */
  public static function getAliasCache() {
    return new FilesystemAdapter('acli_aliases');
  }

  /**
   * @param $application_alias
   *
   * @return mixed
   * @throws AcquiaCliException
   */
  protected function doGetApplicationFromAlias($application_alias) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $acquia_cloud_client->addQuery('filter', 'hosting=@*:' . $application_alias);
    // Allow Cloud users with 'support' role to resolve aliases for applications to
    // which they don't explicitly belong.
    $account_resource = new Account($acquia_cloud_client);
    $account = $account_resource->get();
    if ($account->flags->support) {
      $acquia_cloud_client->addQuery('all', 'true');
    }
    $customer_applications = $acquia_cloud_client->request('get', '/applications');
    $site_prefix = '';
    if ($customer_applications) {
      $customer_application = $customer_applications[0];
      $site_id = $customer_application->hosting->id;
      $parts = explode(':', $site_id);
      $site_prefix = $parts[1];
    }
    else {
      throw new AcquiaCliException("No applications found");
    }

    if ($site_prefix !== $application_alias) {
      throw new AcquiaCliException("Application not found matching the alias {alias}", ['alias' => $application_alias]);
    }

    $this->logger->debug("Found application {$customer_application->uuid} matching alias $application_alias.");

    // Remove the host=@*.$drush_site query as it would persist for future requests.
    $acquia_cloud_client->clearQuery();

    return $customer_application;
  }

  /**
   * @throws AcquiaCliException
   */
  public function requireCloudIdeEnvironment(): void {
    if (!self::isAcquiaCloudIde() || !self::getThisCloudIdeUuid()) {
      throw new AcquiaCliException('This command can only be run inside of an Acquia Cloud IDE');
    }
  }

  /**
   * @param string $ide_uuid
   *
   * @return \stdClass|null
   */
  protected function findIdeSshKeyOnCloud($ide_uuid): ?stdClass {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $ides_resource = new Ides($acquia_cloud_client);
    $ide = $ides_resource->get($ide_uuid);
    $ssh_key_label = SshKeyCommandBase::getIdeSshKeyLabel($ide);
    foreach ($cloud_keys as $cloud_key) {
      if ($cloud_key->label === $ssh_key_label) {
        return $cloud_key;
      }
    }
    return NULL;
  }

  /**
   * @param \stdClass|null $cloud_key
   *
   * @throws AcquiaCliException
   * @throws \Exception
   */
  protected function deleteSshKeyFromCloud(stdClass $cloud_key): void {
    $return_code = $this->executeAcliCommand('ssh-key:delete', [
      '--cloud-key-uuid' => $cloud_key->uuid,
    ]);
    if ($return_code !== 0) {
      throw new AcquiaCliException('Unable to delete SSH key from the Cloud Platform');
    }
  }

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
      if ($latest = $this->hasUpdate()) {
        return $latest;
      }
    } catch (Exception $e) {
      $this->logger->debug("Could not determine if Acquia CLI has a new version available.");
    }
    return FALSE;
  }

  /**
   * Check if an update is available.
   *
   * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
   * @todo unify with consolidation/self-update and support unstable channels
   */
  protected function hasUpdate() {
    $client = $this->getUpdateClient();
    $response = $client->get('https://api.github.com/repos/acquia/cli/releases');
    if ($response->getStatusCode() !== 200) {
      $this->logger->debug('Encountered ' . $response->getStatusCode() . ' error when attempting to check for new ACLI releases on GitHub: ' . $response->getReasonPhrase());
      return FALSE;
    }

    $releases = json_decode($response->getBody());
    if (!isset($releases[0])) {
      $this->logger->debug('No releases found at GitHub repository acquia/cli');
      return FALSE;
    }

    /**
     * @var $version string
     */
    $version = $releases[0]->tag_name;
    $versionStability = VersionParser::parseStability($version);
    $versionIsNewer = version_compare($version, $this->getApplication()->getVersion());
    if ($versionStability === 'stable' && $versionIsNewer) {
      return $version;
    }

    return FALSE;
  }

  /**
   * @param \GuzzleHttp\Client $client
   */
  public function setUpdateClient(\GuzzleHttp\Client $client): void {
    $this->updateClient = $client;
  }

  /**
   * @return \GuzzleHttp\Client
   */
  public function getUpdateClient(): \GuzzleHttp\Client {
    if (!isset($this->updateClient)) {
      $stack = HandlerStack::create();
      $stack->push(new CacheMiddleware(
        new PrivateCacheStrategy(
          new Psr6CacheStorage(
            new FilesystemAdapter('acli')
          )
        )
      ),
        'cache');
      $client = new \GuzzleHttp\Client(['handler' => $stack]);
      $this->setUpdateClient($client);
    }
    return $this->updateClient;
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   *
   * @throws \Exception
   */
  protected function fillMissingRequiredApplicationUuid(InputInterface $input, OutputInterface $output): void {
    if ($input->hasArgument('applicationUuid') && !$input->getArgument('applicationUuid') && $this->getDefinition()->getArgument('applicationUuid')->isRequired()) {
      $output->writeln('Inferring Cloud Application UUID for this command since none was provided...', OutputInterface::VERBOSITY_VERBOSE);
      if ($application_uuid = $this->determineCloudApplication()) {
        $output->writeln("Set application uuid to <options=bold>$application_uuid</>", OutputInterface::VERBOSITY_VERBOSE);
        $input->setArgument('applicationUuid', $application_uuid);
      }
    }
  }

  /**
   * @param InputInterface $input
   *
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function convertApplicationAliasToUuid(InputInterface $input): void {
    if ($input->hasArgument('applicationUuid') && $input->getArgument('applicationUuid')) {
      $application_uuid_argument = $input->getArgument('applicationUuid');
      try {
        self::validateUuid($application_uuid_argument);
      } catch (ValidatorException $validator_exception) {
        // Since this isn't a valid UUID, let's see if it's a valid alias.
        $alias = $this->normalizeAlias($application_uuid_argument);
        try {
          $customer_application = $this->getApplicationFromAlias($alias);
          $input->setArgument('applicationUuid', $customer_application->uuid);
        } catch (AcquiaCliException $exception) {
          throw new AcquiaCliException("The {applicationUuid} argument must be a valid UUID or application alias that is accessible to your Cloud user.");
        }
      }
    }
  }

  /**
   * @param InputInterface $input
   *
   * @param $argument_name
   *
   * @throws AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function convertEnvironmentAliasToUuid(InputInterface $input, $argument_name): void {
    if ($input->hasArgument($argument_name) && $input->getArgument($argument_name)) {
      $env_uuid_argument = $input->getArgument($argument_name);
      try {
        // Environment IDs take the form of [env-num]-[app-uuid].
        $uuid_parts = explode('-', $env_uuid_argument);
        $env_id = $uuid_parts[0];
        unset($uuid_parts[0]);
        $application_uuid = implode('-', $uuid_parts);
        self::validateUuid($application_uuid);
      } catch (ValidatorException $validator_exception) {
        try {
          // Since this isn't a valid environment ID, let's see if it's a valid alias.
          $alias = $env_uuid_argument;
          $alias = $this->normalizeAlias($alias);
          $alias = self::validateEnvironmentAlias($alias);
          $environment = $this->getEnvironmentFromAliasArg($alias);
          $input->setArgument($argument_name, $environment->uuid);
        } catch (AcquiaCliException $exception) {
          throw new AcquiaCliException("{{$argument_name}} must be a valid UUID or site alias.");
        }
      }
    }
  }

  /**
   * @param string $ssh_url
   *   The SSH URL to the server.
   *
   * @return string
   *   The sitegroup. E.g., eemgrasmick.
   */
  public static function getSiteGroupFromSshUrl(string $ssh_url): string {
    $ssh_url_parts = explode('.', $ssh_url);
    $sitegroup = reset($ssh_url_parts);

    return $sitegroup;
  }

  /**
   * @param $cloud_environment
   *
   * @return bool
   */
  protected function isAcsfEnv($cloud_environment): bool {
    if (strpos($cloud_environment->sshUrl, 'enterprise-g1') !== FALSE) {
      return TRUE;
    }
    foreach ($cloud_environment->domains as $domain) {
      if (strpos($domain, 'acsitefactory') !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * @param EnvironmentResponse $cloud_environment
   *
   * @return array
   * @throws AcquiaCliException
   */
  protected function getAcsfSites($cloud_environment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloud_environment->sshUrl);
    $command = ['cat', "/var/www/site-php/$sitegroup.{$cloud_environment->name}/multisite-config.json"];
    $process = $this->sshHelper->executeCommand($cloud_environment, $command, FALSE);
    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE);
    }
    throw new AcquiaCliException("Could not get ACSF sites");
  }

  /**
   * @param EnvironmentResponse $cloud_environment
   *
   * @return array
   * @throws AcquiaCliException
   */
  protected function getCloudSites($cloud_environment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloud_environment->sshUrl);
    $command = ['ls', $this->getCloudSitesPath($cloud_environment, $sitegroup)];
    $process = $this->sshHelper->executeCommand($cloud_environment, $command, FALSE);
    $sites = array_filter(explode("\n", trim($process->getOutput())));
    if ($process->isSuccessful() && $sites) {
      return $sites;
    }

    throw new AcquiaCliException("Could not get Cloud sites for " . $cloud_environment->name);
  }

  protected function getCloudSitesPath($cloud_environment, $sitegroup) {
    if ($cloud_environment->platform === 'cloud-next') {
      $path = "/home/clouduser/{$cloud_environment->name}/sites";
    }
    else {
      $path = "/mnt/files/$sitegroup.{$cloud_environment->name}/sites";
    }
    return $path;
  }

  /**
   * @param EnvironmentResponse $cloud_environment
   *
   * @return mixed
   * @throws AcquiaCliException
   */
  protected function promptChooseAcsfSite($cloud_environment) {
    $choices = [];
    $acsf_sites = $this->getAcsfSites($cloud_environment);
    foreach ($acsf_sites['sites'] as $domain => $acsf_site) {
      $choices[] = "{$acsf_site['name']} ($domain)";
    }
    $choice = $this->io->choice('Choose a site', $choices, $choices[0]);
    $key = array_search($choice, $choices, TRUE);
    $sites = array_values($acsf_sites['sites']);
    $site = $sites[$key];

    return $site['name'];
  }

  /**
   * @param EnvironmentResponse $cloud_environment
   *
   * @return mixed
   * @throws AcquiaCliException
   */
  protected function promptChooseCloudSite($cloud_environment) {
    $sites = $this->getCloudSites($cloud_environment);
    if (count($sites) === 1) {
      $site = reset($sites);
      $this->logger->debug("Only a single Cloud site was detected. Assuming site is $site");
      return $site;
    }
    $this->logger->debug("Multisite detected");
    $this->warnMultisite();
    return $this->io->choice('Choose a site', $sites, $sites[0]);
  }

  /**
   * Migrate from storing preference in acquia-cli.json.
   */
  protected function migrateLegacySendTelemetryPreference(): void {
    $legacy_acli_config_filepath = $this->localMachineHelper->getLocalFilepath(Path::join(dirname($this->cloudConfigFilepath),
      'acquia-cli.json'));
    if ($this->localMachineHelper->getFilesystem()->exists($legacy_acli_config_filepath)) {
      $legacy_acli_config = json_decode(file_get_contents($legacy_acli_config_filepath), TRUE);
      if (array_key_exists('send_telemetry', $legacy_acli_config)) {
        $send_telemetry = $legacy_acli_config['send_telemetry'];
        $this->datastoreCloud->set('send_telemetry', $send_telemetry);
      }
    }
  }

  /**
   * Migrate from storing only a single API key to storing multiple.
   */
  protected function migrateLegacyApiKey(): void {
    if ($this->datastoreCloud
      && $this->datastoreCloud->get('key')
      && $this->datastoreCloud->get('secret')
      && !$this->datastoreCloud->get('acli_key')
      && !$this->datastoreCloud->get('keys')
    ) {
      $uuid = $this->datastoreCloud->get('key');
      $token_info = $this->cloudApiClientService->getClient()->request('get', "/account/tokens/{$uuid}");
      $keys[$uuid] = [
        'label' => $token_info->label,
        'uuid' => $uuid,
        'secret' => $this->datastoreCloud->get('secret'),
      ];
      $this->datastoreCloud->set('keys', $keys);
      $this->datastoreCloud->set('acli_key', $uuid);
    }

    // Convert from serialized to unserialized.
    if ($this->datastoreCloud
      && $this->datastoreCloud->get('acli_key')
      && $this->datastoreCloud->get('keys')
      && is_string($this->datastoreCloud->get('keys'))
      && strpos($this->datastoreCloud->get('keys'), 'a:') === 0
    ) {
      $value = Serializer::unserialize($this->datastoreCloud->get('keys'));
      $this->datastoreCloud->set('keys', $value);
      $this->reAuthenticate($this->cloudCredentials->getCloudKey(), $this->cloudCredentials->getCloudSecret(), $this->cloudCredentials->getBaseUri());
    }
  }

  public static function getLandoInfo() {
    if ($lando_info = AcquiaDrupalEnvironmentDetector::getLandoInfo()) {
      return json_decode($lando_info);
    }
    return NULL;
  }

  public static function isLandoEnv() {
    return (bool) self::getLandoInfo();
  }

  /**
   * @param string $api_key
   * @param string $api_secret
   * @param $base_uri
   */
  protected function reAuthenticate(string $api_key, string $api_secret, $base_uri): void {
    // Client service needs to be reinitialized with new credentials in case
    // this is being run as a sub-command.
    // @see https://github.com/acquia/cli/issues/403
    $this->cloudApiClientService->setConnector(new Connector([
      'key' => $api_key,
      'secret' => $api_secret
    ]), $base_uri);
  }

  protected function warnMultisite(): void {
    $this->io->note("This is a multisite application. Drupal will load the default site unless you've configured sites.php for this environment: https://docs.acquia.com/cloud-platform/develop/drupal/multisite/");
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
   * @param null $output_callback
   *
   * @return bool
   */
  protected function getDrushDatabaseConnectionStatus($output_callback = NULL): bool {
    if (!is_null($this->drushHasActiveDatabaseConnection)) {
      return $this->drushHasActiveDatabaseConnection;
    }
    if ($this->localMachineHelper->commandExists('drush')) {
      $process = $this->localMachineHelper->execute([
        'drush',
        'status',
        '--fields=db-status,drush-version',
        '--format=json',
        '--no-interaction',
      ], $output_callback, $this->dir, FALSE);
      if ($process->isSuccessful()) {
        $drush_status_return_output = json_decode($process->getOutput(), TRUE);
        if (is_array($drush_status_return_output) && array_key_exists('db-status', $drush_status_return_output) && $drush_status_return_output['db-status'] === 'Connected') {
          $this->drushHasActiveDatabaseConnection = TRUE;
          return $this->drushHasActiveDatabaseConnection;
        }
      }
    }

    $this->drushHasActiveDatabaseConnection = FALSE;

    return $this->drushHasActiveDatabaseConnection;
  }

  /**
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   * @param null $output_callback
   *
   * @return string
   * @throws \Exception
   */
  protected function createMySqlDumpOnLocal(string $db_host, string $db_user, string $db_name, string $db_password, $output_callback = NULL): string {
    $this->localMachineHelper->checkRequiredBinariesExist(['mysqldump', 'gzip']);
    $filename = "acli-mysql-dump-{$db_name}.sql.gz";
    $local_temp_dir = sys_get_temp_dir();
    $local_filepath = $local_temp_dir . '/' . $filename;
    $this->logger->debug("Dumping MySQL database to $local_filepath on this machine");
    $this->localMachineHelper->checkRequiredBinariesExist(['mysqldump', 'gzip']);
    if ($output_callback) {
      $output_callback('out', "Dumping MySQL database to $local_filepath on this machine");
    }
    if ($this->localMachineHelper->commandExists('pv')) {
      $command = "MYSQL_PWD={$db_password} mysqldump --host={$db_host} --user={$db_user} {$db_name} | pv --rate --bytes | gzip -9 > $local_filepath";
    }
    else {
      $this->io->warning('Please install `pv` to see progress bar');
      $command = "MYSQL_PWD={$db_password} mysqldump --host={$db_host} --user={$db_user} {$db_name} | gzip -9 > $local_filepath";
    }

    $process = $this->localMachineHelper->executeFromCmd($command, $output_callback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful() || $process->getOutput()) {
      throw new AcquiaCliException('Unable to create a dump of the local database. {message}', ['message' => $process->getErrorOutput()]);
    }

    return $local_filepath;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Exception
   */
  protected function promptOpenBrowserToCreateToken(
    InputInterface $input,
    OutputInterface $output
  ): void {
    if (!$input->getOption('key') || !$input->getOption('secret')) {
      $token_url = 'https://cloud.acquia.com/a/profile/tokens';
      $this->output->writeln("You will need a Cloud Platform API token from <href=$token_url>$token_url</>");

      if (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && $this->io->confirm('Do you want to open this page to generate a token now?')) {
        $this->localMachineHelper->startBrowser($token_url);
      }
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   */
  protected function determineApiKey(InputInterface $input, OutputInterface $output): string {
    if ($input->getOption('key')) {
      $api_key = $input->getOption('key');
      $this->validateApiKey($api_key);
    }
    else {
      $api_key = $this->io->ask('Please enter your API Key', NULL, Closure::fromCallable([$this, 'validateApiKey']));
    }

    return $api_key;
  }

  /**
   * @param $key
   *
   * @return string
   */
  protected function validateApiKey($key): string {
    $violations = Validation::createValidator()->validate($key, [
      new Length(['min' => 10]),
      new NotBlank(),
      new Regex(['pattern' => '/^\S*$/', 'message' => 'The value may not contain spaces']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }
    return $key;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   * @throws \Exception
   */
  protected function determineApiSecret(InputInterface $input, OutputInterface $output): string {
    if ($input->getOption('secret')) {
      $api_secret = $input->getOption('secret');
      $this->validateApiKey($api_secret);
    }
    else {
      $question = new Question('Please enter your API Secret (input will be hidden)');
      $question->setHidden($this->localMachineHelper->useTty());
      $question->setHiddenFallback(TRUE);
      $question->setValidator(Closure::fromCallable([$this, 'validateApiKey']));
      $api_secret = $this->io->askQuestion($question);
    }

    return $api_secret;
  }

}
