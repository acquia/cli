<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\ClientServiceInterface;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\CloudApi\CloudCredentials;
use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\AliasCache;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\LoopHelper;
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
use AcquiaCloudApi\Endpoints\Notifications;
use AcquiaCloudApi\Endpoints\Subscriptions;
use AcquiaCloudApi\Response\AccountResponse;
use AcquiaCloudApi\Response\ApplicationResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\EnvironmentsResponse;
use AcquiaCloudApi\Response\NotificationResponse;
use AcquiaCloudApi\Response\SubscriptionResponse;
use AcquiaLogstream\LogstreamManager;
use ArrayObject;
use Closure;
use Composer\Semver\Comparator;
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
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
use Zumba\Amplitude\Amplitude;

/**
 * Class CommandBase.
 *
 * @package Grasmash\YamlCli\Command
 */
abstract class CommandBase extends Command implements LoggerAwareInterface {

  use LoggerAwareTrait;

  protected InputInterface $input;

  protected OutputInterface $output;

  protected SymfonyStyle $io;

  protected FormatterHelper $formatter;

  private ApplicationResponse $cloudApplication;

  protected TelemetryHelper $telemetryHelper;

  public LocalMachineHelper $localMachineHelper;

  protected CloudDataStore $datastoreCloud;

  protected AcquiaCliDatastore $datastoreAcli;

  protected CloudCredentials|ApiCredentialsInterface $cloudCredentials;

  protected string $projectDir;

  protected ClientService|ClientServiceInterface $cloudApiClientService;

  protected LogstreamManager $logstreamManager;

  public SshHelper $sshHelper;

  protected string $sshDir;

  protected $dir;

  protected $localDbUser;
  protected $localDbPassword;
  protected $localDbName;
  protected $localDbHost;

  protected bool $drushHasActiveDatabaseConnection;

  protected \GuzzleHttp\Client $updateClient;

  protected \GuzzleHttp\Client $httpClient;

  public function __construct(
    LocalMachineHelper $localMachineHelper,
    CloudDataStore $datastoreCloud,
    AcquiaCliDatastore $datastoreAcli,
    ApiCredentialsInterface $cloudCredentials,
    TelemetryHelper $telemetryHelper,
    string $projectDir,
    ClientServiceInterface $cloudApiClientService,
    LogstreamManager $logstreamManager,
    SshHelper $sshHelper,
    string $sshDir,
    LoggerInterface $logger,
    \GuzzleHttp\Client $httpClient
  ) {
    $this->localMachineHelper = $localMachineHelper;
    $this->datastoreCloud = $datastoreCloud;
    $this->datastoreAcli = $datastoreAcli;
    $this->cloudCredentials = $cloudCredentials;
    $this->telemetryHelper = $telemetryHelper;
    $this->projectDir = $projectDir;
    $this->cloudApiClientService = $cloudApiClientService;
    $this->logstreamManager = $logstreamManager;
    $this->sshHelper = $sshHelper;
    $this->sshDir = $sshDir;
    $this->logger = $logger;
    $this->httpClient = $httpClient;
    parent::__construct();
  }

  protected static function getUuidRegexConstraint(): Regex {
    return new Regex([
      'pattern' => '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i',
      'message' => 'This is not a valid UUID.',
    ]);
  }

  public function setProjectDir(string $projectDir): void {
    $this->projectDir = $projectDir;
  }

  public function getProjectDir(): string {
    return $this->projectDir;
  }

  private function setLocalDbUser(): void {
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

  private function setLocalDbPassword(): void {
    $this->localDbPassword = 'drupal';
    if ($lando_info = self::getLandoInfo()) {
      $this->localDbPassword = $lando_info->database->creds->password;
    }
    if (getenv('ACLI_DB_PASSWORD')) {
      $this->localDbPassword = getenv('ACLI_DB_PASSWORD');
    }
  }

  public function getDefaultLocalDbPassword(): mixed {
    if (!isset($this->localDbPassword)) {
      $this->setLocalDbPassword();
    }

    return $this->localDbPassword;
  }

  private function setLocalDbName(): void {
    $this->localDbName = 'drupal';
    if ($lando_info = self::getLandoInfo()) {
      $this->localDbName = $lando_info->database->creds->database;
    }
    if (getenv('ACLI_DB_NAME')) {
      $this->localDbName = getenv('ACLI_DB_NAME');
    }
  }

  public function getDefaultLocalDbName(): mixed {
    if (!isset($this->localDbName)) {
      $this->setLocalDbName();
    }

    return $this->localDbName;
  }

  private function setLocalDbHost(): void {
    $this->localDbHost = 'localhost';
    if ($lando_info = self::getLandoInfo()) {
      $this->localDbHost = $lando_info->database->hostnames[0];
    }
    if (getenv('ACLI_DB_HOST')) {
      $this->localDbHost = getenv('ACLI_DB_HOST');
    }
  }

  public function getDefaultLocalDbHost(): mixed {
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
   * @throws AcquiaCliException
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function initialize(InputInterface $input, OutputInterface $output): void {
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
    $this->telemetryHelper->initialize();
    $this->checkAuthentication();

    $this->fillMissingRequiredApplicationUuid($input, $output);
    $this->convertApplicationAliasToUuid($input);
    $this->convertEnvironmentAliasToUuid($input, 'environmentId');
    $this->convertEnvironmentAliasToUuid($input, 'source-environment');
    $this->convertEnvironmentAliasToUuid($input, 'destination-environment');
    $this->convertEnvironmentAliasToUuid($input, 'source');

    if ($latest = $this->checkForNewVersion()) {
      $this->output->writeln("Acquia CLI {$latest} is available. Run <options=bold>acli self-update</> to update.");
    }
  }

  /**
   * Check if telemetry preference is set, prompt if not.
   */
  public function checkAndPromptTelemetryPreference(): void {
    $send_telemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
    if ($this->getName() !== 'telemetry' && (!isset($send_telemetry)) && $this->input->isInteractive()) {
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
   * @throws \Symfony\Component\Console\Exception\ExceptionInterface
   */
  public function run(InputInterface $input, OutputInterface $output): int {
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
  protected function acceptApplicationUuid(): static {
    $this->addArgument('applicationUuid', InputArgument::OPTIONAL, 'The Cloud Platform application UUID or alias (i.e. an application name optionally prefixed with the realm)')
      ->addUsage(self::getDefaultName() . ' [<applicationAlias>]')
      ->addUsage(self::getDefaultName() . ' myapp')
      ->addUsage(self::getDefaultName() . ' prod:myapp')
      ->addUsage(self::getDefaultName() . ' abcd1234-1111-2222-3333-0e02b2c3d470');

    return $this;
  }

  /**
   * Add argument and usage examples for environmentId.
   */
  protected function acceptEnvironmentId(): static {
    $this->addArgument('environmentId', InputArgument::OPTIONAL, 'The Cloud Platform environment ID or alias (i.e. an application and environment name optionally prefixed with the realm)')
      ->addUsage(self::getDefaultName() . ' [<environmentAlias>]')
      ->addUsage(self::getDefaultName() . ' myapp.dev')
      ->addUsage(self::getDefaultName() . ' prod:myapp.dev')
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
  protected function acceptSite(): self {
    // Do not set a default site in order to force a user prompt.
    $this->addArgument('site', InputArgument::OPTIONAL, 'For a multisite application, the directory name of the site')
      ->addUsage(self::getDefaultName() . ' myapp.dev default');

    return $this;
  }

  /**
   * Indicates whether the command requires the machine to be authenticated with the Cloud Platform.
   */
  protected function commandRequiresAuthentication(): bool {
    // Assume commands require authentication unless they opt out by overriding this method.
    return TRUE;
  }

  /**
   * Prompts the user to choose from a list of available Cloud Platform
   * applications.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function promptChooseSubscription(
    Client $acquia_cloud_client
  ): ?SubscriptionResponse {
    $subscriptions_resource = new Subscriptions($acquia_cloud_client);
    $customer_subscriptions = $subscriptions_resource->getAll();

    if (!$customer_subscriptions->count()) {
      throw new AcquiaCliException("You have no Cloud subscriptions.");
    }
    return $this->promptChooseFromObjectsOrArrays(
      $customer_subscriptions,
      'uuid',
      'name',
      'Select a Cloud Platform subscription:'
    );
  }

  /**
   * Prompts the user to choose from a list of available Cloud Platform
   * applications.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function promptChooseApplication(
    Client $acquia_cloud_client
  ): object|array|null {
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();

    if (!$customer_applications->count()) {
      throw new AcquiaCliException("You have no Cloud applications.");
    }
    return $this->promptChooseFromObjectsOrArrays(
      $customer_applications,
      'uuid',
      'name',
      'Select a Cloud Platform application:'
    );
  }

  /**
   * Prompts the user to choose from a list of environments for a given Cloud Platform application.
   */
  private function promptChooseEnvironment(
    Client $acquia_cloud_client,
    string $application_uuid
  ): object|array|null {
    $environment_resource = new Environments($acquia_cloud_client);
    $environments = $environment_resource->getAll($application_uuid);
    // @todo Make sure there are actually environments here.
    return $this->promptChooseFromObjectsOrArrays(
      $environments,
      'uuid',
      'name',
      'Select a Cloud Platform environment:'
    );
  }

  /**
   * Prompts the user to choose from a list of logs for a given Cloud Platform environment.
   */
  protected function promptChooseLogs(): object|array|null {
    $logs = array_map(static function ($log_type, $log_label) {
      return [
        'type' => $log_type,
        'label' => $log_label,
      ];
    }, array_keys(LogstreamManager::AVAILABLE_TYPES), LogstreamManager::AVAILABLE_TYPES);
    return $this->promptChooseFromObjectsOrArrays(
      $logs,
      'type',
      'label',
      'Select one or more logs as a comma-separated list:',
      TRUE
    );
  }

  /**
   * Prompt a user to choose from a list.
   *
   * The list is generated from an array of objects. The objects much have at least one unique property and one
   * property that can be used as a human-readable label.
   *
   * @param array[]|object[] $items An array of objects or arrays.
   * @param string $unique_property The property of the $item that will be used to identify the object.
   */
  public function promptChooseFromObjectsOrArrays(array|ArrayObject $items, string $unique_property, string $label_property, string $question_text, bool $multiselect = FALSE): object|array|null {
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
        else if ($item->$unique_property === $identifier) {
          return $item;
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
          else if ($item->$unique_property === $identifier) {
            $chosen[] = $item;
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
  private function getGitConfig(): ?array {
    $file_path = $this->projectDir . '/.git/config';
    if (file_exists($file_path)) {
      return parse_ini_file($file_path, TRUE);
    }

    return NULL;
  }

  /**
   * Gets an array of git remotes from a .git/config array.
   *
   * @param array $git_config
   * @return array
   *   A flat array of git remote urls.
   */
  private function getGitRemotes(array $git_config): array {
    $local_vcs_remotes = [];
    foreach ($git_config as $section_name => $section) {
      if ((str_contains($section_name, 'remote ')) &&
        (strpos($section['url'], 'acquia.com') || strpos($section['url'], 'acquia-sites.com'))
      ) {
        $local_vcs_remotes[] = $section['url'];
      }
    }

    return $local_vcs_remotes;
  }

  /**
   * @param array $local_git_remotes
   */
  private function findCloudApplicationByGitUrl(
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

  protected function createTable(OutputInterface $output, string $title, $headers, $widths): Table {
    $terminal_width = (new Terminal())->getWidth();
    $terminal_width *= .90;
    $table = new Table($output);
    $table->setHeaders($headers);
    $table->setHeaderTitle($title);
    $set_widths = static function ($width) use ($terminal_width) {
      return (int) ($terminal_width * $width);
    };
    $table->setColumnWidths(array_map($set_widths, $widths));
    return $table;
  }

  /**
   * @param array $local_git_remotes
   */
  private function searchApplicationEnvironmentsForGitUrl(
    ApplicationResponse $application,
    EnvironmentsResponse $application_environments,
    array $local_git_remotes
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
   */
  protected function inferCloudAppFromLocalGitConfig(
    Client $acquia_cloud_client
    ): ?ApplicationResponse {
    if ($this->projectDir && $this->input->isInteractive()) {
      $this->output->writeln("There is no Cloud Platform application linked to <options=bold>{$this->projectDir}/.git</>.");
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

          $this->output->writeln('<comment>Could not find a matching Cloud application.</comment>');
          return NULL;
        }
      }
    }

    return NULL;
  }

  /**
   * Determine the Cloud environment.
   *
   * @throws \Exception
   * @return string
   *   The environment UUID.
   */
  protected function determineCloudEnvironment(): string {
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determineCloudSubscription(): ?SubscriptionResponse {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();

    if ($this->input->hasArgument('subscriptionUuid') && $this->input->getArgument('subscriptionUuid')) {
      $cloud_subscription_uuid = $this->input->getArgument('subscriptionUuid');
      self::validateUuid($cloud_subscription_uuid);
      return (new Subscriptions($acquia_cloud_client))->get($cloud_subscription_uuid);
    }

    // Finally, just ask.
    if ($this->input->isInteractive() && $subscription = $this->promptChooseSubscription($acquia_cloud_client)) {
      return $subscription;
    }

    return NULL;

  }

  /**
   * Determine the Cloud application.
   *
   * @throws \Exception
   */
  protected function determineCloudApplication(bool $prompt_link_app = FALSE): ?string {
    $application_uuid = $this->doDetermineCloudApplication();
    if (!isset($application_uuid)) {
      throw new AcquiaCliException("Could not determine Cloud Application. Run this command interactively or use `acli link` to link a Cloud Application before running non-interactively.");
    }

    $application = $this->getCloudApplication($application_uuid);
    // No point in trying to link a directory that's not a repo.
    if (!empty($this->projectDir) && !$this->getCloudUuidFromDatastore()) {
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
   * @throws \Exception
   */
  protected function doDetermineCloudApplication(): mixed {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();

    if ($this->input->hasArgument('applicationUuid') && $this->input->getArgument('applicationUuid')) {
      $cloud_application_uuid = $this->input->getArgument('applicationUuid');
      return self::validateUuid($cloud_application_uuid);
    }

    // Try local project info.
    if ($application_uuid = $this->getCloudUuidFromDatastore()) {
      $this->logger->debug("Using Cloud application UUID: $application_uuid from {$this->datastoreAcli->filepath}");
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

  protected function getCloudApplicationUuidFromBltYaml(): ?string {
    $blt_yaml_file_path = Path::join($this->projectDir, 'blt', 'blt.yml');
    if (file_exists($blt_yaml_file_path)) {
      $contents = Yaml::parseFile($blt_yaml_file_path);
      if (array_key_exists('cloud', $contents) && array_key_exists('appId', $contents['cloud'])) {
        return $contents['cloud']['appId'];
      }
    }

    return NULL;
  }

  public static function validateUuid(string $uuid): string {
    $violations = Validation::createValidator()->validate($uuid, [
      new Length([
        'value' => 36,
      ]),
      self::getUuidRegexConstraint(),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $uuid;
  }

  /**
   * @throws \Exception
   */
  private function saveCloudUuidToDatastore(ApplicationResponse $application): bool {
    $this->datastoreAcli->set('cloud_app_uuid', $application->uuid);
    $this->io->success("The Cloud application {$application->name} has been linked to this repository by writing to {$this->datastoreAcli->filepath}");

    return TRUE;
  }

  protected function getCloudUuidFromDatastore(): mixed {
    return $this->datastoreAcli->get('cloud_app_uuid');
  }

  /**
   * @throws \Exception
   */
  private function promptLinkApplication(
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
    if (!$this->projectDir) {
      throw new AcquiaCliException('Could not find a local Drupal project. Looked for `docroot/index.php` in current and parent directories. Execute this command from within a Drupal project directory.');
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
  protected static function getThisCloudIdeCloudAppUuid(): bool|array|string {
    return getenv('ACQUIA_APPLICATION_UUID');
  }

  /**
   * Get the UUID from a Cloud IDE's environmental variable.
   *
   * This command assumes it is being run inside a Cloud IDE.
   */
  public static function getThisCloudIdeUuid(): false|string {
    return getenv('REMOTEIDE_UUID');
  }

  protected function getCloudApplication(string $application_uuid): ApplicationResponse {
    $applications_resource = new Applications($this->cloudApiClientService->getClient());
    return $applications_resource->get($application_uuid);
  }

  /**
   * @throws \Exception
   */
  protected function getCloudEnvironment(string $environment_id): EnvironmentResponse {
    $environment_resource = new Environments($this->cloudApiClientService->getClient());

    return $environment_resource->get($environment_id);
  }

  public static function validateEnvironmentAlias(string $alias): string {
    $violations = Validation::createValidator()->validate($alias, [
      new Length(['min' => 5]),
      new NotBlank(),
      new Regex(['pattern' => '/.+\..+/', 'message' => 'You must enter either an environment ID or alias. Environment aliases must match the pattern [app-name].[env]']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $alias;
  }

  protected function normalizeAlias(string $alias): string {
    return str_replace('@', '', $alias);
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getEnvironmentFromAliasArg(string $alias): EnvironmentResponse {
    return $this->getEnvFromAlias($alias);
  }

  /**
   * @param $alias
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function getEnvFromAlias($alias): EnvironmentResponse {
    return self::getAliasCache()->get($alias, function () use ($alias) {
      return $this->doGetEnvFromAlias($alias);
    });
  }

  /**
   * @param $alias
   * @throws AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function doGetEnvFromAlias($alias): EnvironmentResponse {
    $site_env_parts = explode('.', $alias);
    [$application_alias, $environment_alias] = $site_env_parts;
    $this->logger->debug("Searching for an environment matching alias $application_alias.$environment_alias.");
    $customer_application = $this->getApplicationFromAlias($application_alias);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
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
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function getApplicationFromAlias(string $application_alias): mixed {
    return self::getAliasCache()
      ->get($application_alias, function () use ($application_alias) {
        return $this->doGetApplicationFromAlias($application_alias);
      });
  }

  /**
   * Return the ACLI alias cache.
   */
  public static function getAliasCache(): AliasCache {
    return new AliasCache('acli_aliases');
  }

  /**
   * @param $application_alias
   * @throws AcquiaCliException
   */
  private function doGetApplicationFromAlias($application_alias): mixed {
    if (!strpos($application_alias, ':')) {
      $application_alias = '*:' . $application_alias;
    }
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    // No need to clear this query later since getClient() is a factory method.
    $acquia_cloud_client->addQuery('filter', 'hosting=@' . $application_alias);
    // Allow Cloud Platform users with 'support' role to resolve aliases for applications to
    // which they don't explicitly belong.
    $account_resource = new Account($acquia_cloud_client);
    $account = $account_resource->get();
    if ($account->flags->support) {
      $acquia_cloud_client->addQuery('all', 'true');
    }
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();
    if (count($customer_applications) === 0) {
      throw new AcquiaCliException("No applications match the alias {applicationAlias}", ['applicationAlias' => $application_alias]);
    }
    if (count($customer_applications) > 1) {
      $callback = static function ($element) {
        return $element->hosting->id;
      };
      $aliases = array_map($callback, (array) $customer_applications);
      $this->io->error(sprintf("Use a unique application alias: %s", implode(', ', $aliases)));
      throw new AcquiaCliException("Multiple applications match the alias {applicationAlias}", ['applicationAlias' => $application_alias]);
    }

    $customer_application = $customer_applications[0];

    $this->logger->debug("Found application {$customer_application->uuid} matching alias $application_alias.");

    return $customer_application;
  }

  /**
   * @throws AcquiaCliException
   */
  protected function requireCloudIdeEnvironment(): void {
    if (!self::isAcquiaCloudIde() || !self::getThisCloudIdeUuid()) {
      throw new AcquiaCliException('This command can only be run inside of an Acquia Cloud IDE');
    }
  }

  /**
   * @return \stdClass|null
   */
  protected function findIdeSshKeyOnCloud(string $ide_uuid): ?stdClass {
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
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function checkForNewVersion(): bool|string {
    // Input not set if called from an exception listener.
    if (!isset($this->input)) {
      return FALSE;
    }
    // Running on API commands would corrupt JSON output.
    if (str_contains($this->input->getArgument('command'), 'api:')
      || str_contains($this->input->getArgument('command'), 'acsf:')) {
      return FALSE;
    }
    // Bail in Cloud IDEs to avoid hitting GitHub API rate limits.
    if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      return FALSE;
    }
    try {
      if ($latest = $this->hasUpdate()) {
        return $latest;
      }
    }
    catch (Exception) {
      $this->logger->debug("Could not determine if Acquia CLI has a new version available.");
    }
    return FALSE;
  }

  /**
   * Check if an update is available.
   *
   * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
   * @throws \UnexpectedValueException
   * @todo unify with consolidation/self-update and support unstable channels
   */
  protected function hasUpdate(): bool|string {
    $versionParser = new VersionParser();
    // Fail fast on development builds (throw UnexpectedValueException).
    $currentVersion = $versionParser->normalize($this->getApplication()->getVersion());
    $client = $this->getUpdateClient();
    $response = $client->get('https://api.github.com/repos/acquia/cli/releases');
    if ($response->getStatusCode() !== 200) {
      $this->logger->debug('Encountered ' . $response->getStatusCode() . ' error when attempting to check for new ACLI releases on GitHub: ' . $response->getReasonPhrase());
      return FALSE;
    }

    $releases = json_decode($response->getBody(), FALSE, 512, JSON_THROW_ON_ERROR);
    if (!isset($releases[0])) {
      $this->logger->debug('No releases found at GitHub repository acquia/cli');
      return FALSE;
    }

    foreach ($releases as $release) {
      if (!$release->prerelease) {
        /**
         * @var $version string
         */
        $version = $release->tag_name;
        $versionStability = VersionParser::parseStability($version);
        $versionIsNewer = Comparator::greaterThan($versionParser->normalize($version), $currentVersion);
        if ($versionStability === 'stable' && $versionIsNewer) {
          return $version;
        }
        return FALSE;
      }
    }
    return FALSE;
  }

  public function setUpdateClient(\GuzzleHttp\Client $client): void {
    $this->updateClient = $client;
  }

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
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function convertApplicationAliasToUuid(InputInterface $input): void {
    if ($input->hasArgument('applicationUuid') && $input->getArgument('applicationUuid')) {
      $application_uuid_argument = $input->getArgument('applicationUuid');
      $application_uuid = $this->validateApplicationUuid($application_uuid_argument);
      $input->setArgument('applicationUuid', $application_uuid);
    }
  }

  /**
   * @param $argument_name
   * @throws AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function convertEnvironmentAliasToUuid(InputInterface $input, $argument_name): void {
    if ($input->hasArgument($argument_name) && $input->getArgument($argument_name)) {
      $env_uuid_argument = $input->getArgument($argument_name);
      $environment_uuid = $this->validateEnvironmentUuid($env_uuid_argument, $argument_name);
      $input->setArgument($argument_name, $environment_uuid);
    }
  }

  /**
   * @param string $ssh_url
   *   The SSH URL to the server.
   * @return string
   *   The sitegroup. E.g., eemgrasmick.
   */
  public static function getSiteGroupFromSshUrl(string $ssh_url): string {
    $ssh_url_parts = explode('.', $ssh_url);
    return reset($ssh_url_parts);
  }

  /**
   * @param $cloud_environment
   */
  protected function isAcsfEnv($cloud_environment): bool {
    if (str_contains($cloud_environment->sshUrl, 'enterprise-g1')) {
      return TRUE;
    }
    foreach ($cloud_environment->domains as $domain) {
      if (str_contains($domain, 'acsitefactory')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * @return array
   * @throws AcquiaCliException
   * @throws \JsonException
   */
  protected function getAcsfSites(EnvironmentResponse $cloud_environment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloud_environment->sshUrl);
    $command = ['cat', "/var/www/site-php/$sitegroup.{$cloud_environment->name}/multisite-config.json"];
    $process = $this->sshHelper->executeCommand($cloud_environment, $command, FALSE);
    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    throw new AcquiaCliException("Could not get ACSF sites");
  }

  /**
   * @return array
   * @throws AcquiaCliException
   */
  private function getCloudSites(EnvironmentResponse $cloud_environment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloud_environment->sshUrl);
    $command = ['ls', $this->getCloudSitesPath($cloud_environment, $sitegroup)];
    $process = $this->sshHelper->executeCommand($cloud_environment, $command, FALSE);
    $sites = array_filter(explode("\n", trim($process->getOutput())));
    if ($process->isSuccessful() && $sites) {
      return $sites;
    }

    throw new AcquiaCliException("Could not get Cloud sites for " . $cloud_environment->name);
  }

  protected function getCloudSitesPath($cloud_environment, $sitegroup): string {
    if ($cloud_environment->platform === 'cloud-next') {
      $path = "/home/clouduser/{$cloud_environment->name}/sites";
    }
    else {
      $path = "/mnt/files/$sitegroup.{$cloud_environment->name}/sites";
    }
    return $path;
  }

  /**
   * @throws AcquiaCliException
   * @throws \JsonException
   * @throws \JsonException
   */
  protected function promptChooseAcsfSite(EnvironmentResponse $cloud_environment): mixed {
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
   * @throws AcquiaCliException
   */
  protected function promptChooseCloudSite(EnvironmentResponse $cloud_environment): mixed {
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

  public static function getLandoInfo() {
    if ($lando_info = AcquiaDrupalEnvironmentDetector::getLandoInfo()) {
      return json_decode($lando_info, FALSE, 512, JSON_THROW_ON_ERROR);
    }
    return NULL;
  }

  public static function isLandoEnv(): bool {
    return (bool) self::getLandoInfo();
  }

  /**
   * @param $base_uri
   */
  protected function reAuthenticate(string $api_key, string $api_secret, ?string $base_uri, ?string $accounts_uri): void {
    // Client service needs to be reinitialized with new credentials in case
    // this is being run as a sub-command.
    // @see https://github.com/acquia/cli/issues/403
    $this->cloudApiClientService->setConnector(new Connector([
      'key' => $api_key,
      'secret' => $api_secret
    ], $base_uri, $accounts_uri));
  }

  private function warnMultisite(): void {
    $this->io->note("This is a multisite application. Drupal will load the default site unless you've configured sites.php for this environment: https://docs.acquia.com/cloud-platform/develop/drupal/multisite/");
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function setDirAndRequireProjectCwd(InputInterface $input): void {
    $this->determineDir($input);
    if ($this->dir !== '/home/ide/project' && AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      throw new AcquiaCliException('Run this command from the {dir} directory', ['dir' => '/home/ide/project']);
    }
  }

  protected function determineDir(InputInterface $input): void {
    if (isset($this->dir)) {
      return;
    }

    if ($input->hasOption('dir') && $dir = $input->getOption('dir')) {
      $this->dir = $dir;
    }
    elseif ($this->projectDir) {
      $this->dir = $this->projectDir;
    }
    else {
      $this->dir = getcwd();
    }
  }

  protected function getOutputCallback(OutputInterface $output, Checklist $checklist): Closure {
    return static function ($type, $buffer) use ($checklist, $output): void {
      if (!$output->isVerbose() && $checklist->getItems()) {
        $checklist->updateProgressBar($buffer);
      }
      $output->writeln($buffer, OutputInterface::VERBOSITY_VERY_VERBOSE);
    };
  }

  /**
   * @throws \JsonException
   * @throws \JsonException
   */
  protected function getDrushDatabaseConnectionStatus(Closure $output_callback = NULL): bool {
    if (isset($this->drushHasActiveDatabaseConnection)) {
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
        $drush_status_return_output = json_decode($process->getOutput(), TRUE, 512);
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
   * @throws \Exception
   */
  protected function createMySqlDumpOnLocal(string $db_host, string $db_user, string $db_name, string $db_password, Closure $output_callback = NULL): string {
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
      $this->io->warning('Install `pv` to see progress bar');
      $command = "MYSQL_PWD={$db_password} mysqldump --host={$db_host} --user={$db_user} {$db_name} | gzip -9 > $local_filepath";
    }

    $process = $this->localMachineHelper->executeFromCmd($command, $output_callback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful() || $process->getOutput()) {
      throw new AcquiaCliException('Unable to create a dump of the local database. {message}', ['message' => $process->getErrorOutput()]);
    }

    return $local_filepath;
  }

  /**
   * @throws \Exception
   */
  protected function promptOpenBrowserToCreateToken(
    InputInterface $input
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determineApiKey(InputInterface $input): string {
    $key = $this->determineOption('key', $input, FALSE, Closure::fromCallable([$this, 'validateApiKey']));
    if (is_null($key)) {
      throw new AcquiaCliException('Secret cannot be empty');
    }
    return $key;
  }

  /**
   * @param $key
   */
  private function validateApiKey($key): string {
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
   * @throws \Exception
   */
  protected function determineApiSecret(InputInterface $input): string {
    $secret = $this->determineOption('secret', $input, TRUE, Closure::fromCallable([$this, 'validateApiKey']));
    if (is_null($secret)) {
      throw new AcquiaCliException('Secret cannot be empty');
    }
    return $secret;
  }

  /**
   * Get an option, either passed as an argument or via interactive prompt.
   *
   * Default can be passed explicitly, separately from the option default,
   * because Symfony does not make a distinction between an option value set
   * explicitly or by default. In other words, we can't prompt for the value of
   * an option that already has a default value.
   *
   * @param string $option_name
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param bool $hidden
   * @param \Closure|null $validator
   * @param string|null $default
   * @return string|null
   */
  protected function determineOption(string $option_name, InputInterface $input, bool $hidden = FALSE, ?Closure $validator = NULL, ?string $default = NULL): ?string {
    if ($option_value = $input->getOption($option_name)) {
      if (isset($validator)) {
        $validator($option_value);
      }
      return $option_value;
    }
    $option = $this->getDefinition()->getOption($option_name);
    $option_shortcut = $option->getShortcut();
    if ($option_shortcut) {
      $message = $option->getDescription() . " (option <options=bold>-$option_shortcut</>, <options=bold>--$option_name</>) is required";
    }
    else {
      $message = $option->getDescription() . " (option <options=bold>--$option_name</>) is required";
    }
    $message .= $hidden ? ' (input will be hidden)' : '';
    $question = new Question($message, $default);
    $question->setHidden($this->localMachineHelper->useTty() && $hidden);
    $question->setHiddenFallback($hidden);
    if (isset($validator)) {
      $question->setValidator($validator);
    }
    $option_value = $this->io->askQuestion($question);
    $input->setOption($option_name, $option_value);
    return $option_value;
  }

  /**
   * Get the first environment for a given Cloud application matching a filter.
   */
  private function getAnyAhEnvironment(string $cloud_app_uuid, callable $filter): ?EnvironmentResponse {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environment_resource = new Environments($acquia_cloud_client);
    /** @var EnvironmentResponse[] $application_environments */
    $application_environments = iterator_to_array($environment_resource->getAll($cloud_app_uuid));
    $candidates = array_filter($application_environments, $filter);
    return reset($candidates);
  }

  /**
   * Get the first non-prod environment for a given Cloud application.
   *
   * @throws \Exception
   */
  protected function getAnyNonProdAhEnvironment(string $cloud_app_uuid): ?EnvironmentResponse {
    return $this->getAnyAhEnvironment($cloud_app_uuid, function ($environment) {
      return !$environment->flags->production;
    });
  }

  /**
   * Get the first prod environment for a given Cloud application.
   *
   * @throws \Exception
   */
  protected function getAnyProdAhEnvironment(string $cloud_app_uuid): ?EnvironmentResponse {
    return $this->getAnyAhEnvironment($cloud_app_uuid, function ($environment) {
      return $environment->flags->production;
    });
  }

  /**
   * Get the first VCS URL for a given Cloud application.
   */
  protected function getAnyVcsUrl(string $cloud_app_uuid): string {
    $environment = $this->getAnyAhEnvironment($cloud_app_uuid, function () {
      return TRUE;
    });
    return $environment->vcs->url;
  }

  /**
   * @param $application_uuid_argument
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function validateApplicationUuid($application_uuid_argument): mixed {
    try {
      self::validateUuid($application_uuid_argument);
    }
    catch (ValidatorException) {
      // Since this isn't a valid UUID, let's see if it's a valid alias.
      $alias = $this->normalizeAlias($application_uuid_argument);
      return $this->getApplicationFromAlias($alias)->uuid;
    }
    return $application_uuid_argument;
  }

  /**
   * @param $env_uuid_argument
   * @param $argument_name
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function validateEnvironmentUuid($env_uuid_argument, $argument_name): string {
    try {
      // Environment IDs take the form of [env-num]-[app-uuid].
      $uuid_parts = explode('-', $env_uuid_argument);
      $env_id = $uuid_parts[0];
      unset($uuid_parts[0]);
      $application_uuid = implode('-', $uuid_parts);
      self::validateUuid($application_uuid);
    }
    catch (ValidatorException $validator_exception) {
      try {
        // Since this isn't a valid environment ID, let's see if it's a valid alias.
        $alias = $env_uuid_argument;
        $alias = $this->normalizeAlias($alias);
        $alias = self::validateEnvironmentAlias($alias);
        return $this->getEnvironmentFromAliasArg($alias)->uuid;
      }
      catch (AcquiaCliException $exception) {
        throw new AcquiaCliException("{{$argument_name}} must be a valid UUID or site alias.");
      }
    }
    return $env_uuid_argument;
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function checkAuthentication(): void {
    if ($this->commandRequiresAuthentication() && !$this->cloudApiClientService->isMachineAuthenticated()) {
      throw new AcquiaCliException('This machine is not yet authenticated with the Cloud Platform. Run `acli auth:login`');
    }
  }

  protected function waitForNotificationToComplete(Client $acquia_cloud_client, string $uuid, string $message, callable $success = NULL): void {
    $notifications_resource = new Notifications($acquia_cloud_client);
    $notification = NULL;
    $checkNotificationStatus = static function () use ($notifications_resource, &$notification, $uuid) {
      $notification = $notifications_resource->get($uuid);
      return $notification->status !== 'in-progress';
    };
    if ($success === NULL) {
      $success = function () use (&$notification): void {
        $this->writeCompletedMessage($notification);
      };
    }
    LoopHelper::getLoopy($this->output, $this->io, $this->logger, $message, $checkNotificationStatus, $success);
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function writeCompletedMessage(NotificationResponse $notification): void {
    if ($notification->status === 'completed') {
      $this->io->success("The task with notification uuid {$notification->uuid} completed");
    }
    else if ($notification->status === 'failed') {
      $this->io->error("The task with notification uuid {$notification->uuid} failed");
    }
    else {
      throw new AcquiaCliException("Unknown task status: {$notification->status}");
    }
    $duration = strtotime($notification->completed_at) - strtotime($notification->created_at);
    $completed_at = date("D M j G:i:s T Y", strtotime($notification->completed_at));
    $this->io->writeln("Progress: {$notification->progress}");
    $this->io->writeln("Completed: $completed_at");
    $this->io->writeln("Task type: {$notification->label}");
    $this->io->writeln("Duration: $duration seconds");
  }

  protected function getNotificationUuidFromResponse(object $response): string {
    if (property_exists($response, 'links')) {
      $links = $response->links;
    }
    else {
      $links = $response->_links;
    }
    $notification_url = $links->notification->href;
    $url_parts = explode('/', $notification_url);
    return $url_parts[5];
  }

  /**
   * @param array $required_permissions
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validateRequiredCloudPermissions(Client $acquia_cloud_client, ?string $cloud_application_uuid, AccountResponse $account, array $required_permissions): void {
    $permissions = $acquia_cloud_client->request('get', "/applications/{$cloud_application_uuid}/permissions");
    $keyed_permissions = [];
    foreach ($permissions as $permission) {
      $keyed_permissions[$permission->name] = $permission;
    }
    foreach ($required_permissions as $name) {
      if (!array_key_exists($name, $keyed_permissions)) {
        throw new AcquiaCliException("The Acquia Cloud Platform account {account} does not have the required '{name}' permission. Add the permissions to this user or use an API Token belonging to a different Acquia Cloud Platform user.", [
          'account' => $account->mail,
          'name' => $name
        ]);
      }
    }
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validatePhpVersion(string $version): string {
    $violations = Validation::createValidator()->validate($version, [
      new Length(['min' => 3]),
      new NotBlank(),
      new Regex(['pattern' => '/^\S*$/', 'message' => 'The value may not contain spaces']),
      new Regex(['pattern' => '/[0-9]{1}\.[0-9]{1}/', 'message' => 'The value must be in the format "x.y"']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $version;
  }

}
