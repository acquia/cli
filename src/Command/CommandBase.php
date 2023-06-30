<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\CloudApi\ClientService;
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
use AcquiaCloudApi\Endpoints\Organizations;
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

abstract class CommandBase extends Command implements LoggerAwareInterface {

  use LoggerAwareTrait;

  protected InputInterface $input;

  protected OutputInterface $output;

  protected SymfonyStyle $io;
  protected FormatterHelper $formatter;
  private ApplicationResponse $cloudApplication;

  protected string $dir;
  protected string $localDbUser = 'drupal';
  protected string $localDbPassword = 'drupal';
  protected string $localDbName = 'drupal';
  protected string $localDbHost = 'localhost';
  protected bool $drushHasActiveDatabaseConnection;
  protected \GuzzleHttp\Client $updateClient;

  public function __construct(
    public LocalMachineHelper $localMachineHelper,
    protected CloudDataStore $datastoreCloud,
    protected AcquiaCliDatastore $datastoreAcli,
    protected ApiCredentialsInterface $cloudCredentials,
    protected TelemetryHelper $telemetryHelper,
    protected string $projectDir,
    protected ClientService $cloudApiClientService,
    protected LogstreamManager $logstreamManager,
    public SshHelper $sshHelper,
    protected string $sshDir,
    LoggerInterface $logger,
    protected \GuzzleHttp\Client $httpClient
  ) {
    $this->logger = $logger;
    $this->setLocalDbPassword();
    $this->setLocalDbUser();
    $this->setLocalDbName();
    $this->setLocalDbHost();
    parent::__construct();
    if ($this->commandRequiresAuthentication()) {
      $this->appendHelp('This command requires authentication via the Cloud Platform API.');
    }
    if ($this->commandRequiresDatabase()) {
      $this->appendHelp('This command requires an active database connection. Set the following environment variables prior to running this command: '
        . 'ACLI_DB_HOST, ACLI_DB_NAME, ACLI_DB_USER, ACLI_DB_PASSWORD');
    }
  }

  public function appendHelp(string $helpText): void {
    $currentHelp = $this->getHelp();
    $helpText = $currentHelp ? $currentHelp . "\n" . $helpText : $currentHelp . $helpText;
    $this->setHelp($helpText);
  }

  protected static function getUuidRegexConstraint(): Regex {
    return new Regex([
      'message' => 'This is not a valid UUID.',
      'pattern' => '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i',
    ]);
  }

  public function setProjectDir(string $projectDir): void {
    $this->projectDir = $projectDir;
  }

  public function getProjectDir(): string {
    return $this->projectDir;
  }

  private function setLocalDbUser(): void {
    if (getenv('ACLI_DB_USER')) {
      $this->localDbUser = getenv('ACLI_DB_USER');
    }
  }

  public function getLocalDbUser(): string {
    return $this->localDbUser;
  }

  private function setLocalDbPassword(): void {
    if (getenv('ACLI_DB_PASSWORD')) {
      $this->localDbPassword = getenv('ACLI_DB_PASSWORD');
    }
  }

  public function getLocalDbPassword(): string {
    return $this->localDbPassword;
  }

  private function setLocalDbName(): void {
    if (getenv('ACLI_DB_NAME')) {
      $this->localDbName = getenv('ACLI_DB_NAME');
    }
  }

  public function getLocalDbName(): string {
    return $this->localDbName;
  }

  private function setLocalDbHost(): void {
    if (getenv('ACLI_DB_HOST')) {
      $this->localDbHost = getenv('ACLI_DB_HOST');
    }
  }

  public function getLocalDbHost(): string {
    return $this->localDbHost;
  }

  /**
   * Initializes the command just after the input has been validated.
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
    $this->convertUserAliasToUuid($input, 'userUuid', 'organizationUuid');
    $this->convertEnvironmentAliasToUuid($input, 'environmentId');
    $this->convertEnvironmentAliasToUuid($input, 'source-environment');
    $this->convertEnvironmentAliasToUuid($input, 'destination-environment');
    $this->convertEnvironmentAliasToUuid($input, 'source');

    if ($latest = $this->checkForNewVersion()) {
      $this->output->writeln("Acquia CLI $latest is available. Run <options=bold>acli self-update</> to update.");
    }
  }

  /**
   * Check if telemetry preference is set, prompt if not.
   */
  public function checkAndPromptTelemetryPreference(): void {
    $sendTelemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
    if ($this->getName() !== 'telemetry' && (!isset($sendTelemetry)) && $this->input->isInteractive()) {
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

  public function run(InputInterface $input, OutputInterface $output): int {
    $exitCode = parent::run($input, $output);
    if ($exitCode === 0 && in_array($input->getFirstArgument(), ['self-update', 'update'])) {
      // Exit immediately to avoid loading additional classes breaking updates.
      // @see https://github.com/acquia/cli/issues/218
      return $exitCode;
    }
    $eventProperties = [
      'app_version' => $this->getApplication()->getVersion(),
      'arguments' => $input->getArguments(),
      'exit_code' => $exitCode,
      'options' => $input->getOptions(),
      'os_name' => OsInfo::os(),
      'os_version' => OsInfo::version(),
      'platform' => OsInfo::family(),
    ];
    Amplitude::getInstance()->queueEvent('Ran command', $eventProperties);

    return $exitCode;
  }

  /**
   * Add argument and usage examples for applicationUuid.
   */
  protected function acceptApplicationUuid(): static {
    $this->addArgument('applicationUuid', InputArgument::OPTIONAL, 'The Cloud Platform application UUID or alias (i.e. an application name optionally prefixed with the realm)')
      ->addUsage('[<applicationAlias>]')
      ->addUsage('myapp')
      ->addUsage('prod:myapp')
      ->addUsage('abcd1234-1111-2222-3333-0e02b2c3d470');

    return $this;
  }

  /**
   * Add argument and usage examples for environmentId.
   */
  protected function acceptEnvironmentId(): static {
    $this->addArgument('environmentId', InputArgument::OPTIONAL, 'The Cloud Platform environment ID or alias (i.e. an application and environment name optionally prefixed with the realm)')
      ->addUsage('[<environmentAlias>]')
      ->addUsage('myapp.dev')
      ->addUsage('prod:myapp.dev')
      ->addUsage('12345-abcd1234-1111-2222-3333-0e02b2c3d470');

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
      ->addUsage('myapp.dev default');

    return $this;
  }

  /**
   * Indicates whether the command requires the machine to be authenticated with the Cloud Platform.
   */
  protected function commandRequiresAuthentication(): bool {
    // Assume commands require authentication unless they opt out by overriding this method.
    return TRUE;
  }

  protected function commandRequiresDatabase(): bool {
    return FALSE;
  }

  /**
   * Prompts the user to choose from a list of available Cloud Platform
   * applications.
   */
  private function promptChooseSubscription(
    Client $acquiaCloudClient
  ): ?SubscriptionResponse {
    $subscriptionsResource = new Subscriptions($acquiaCloudClient);
    $customerSubscriptions = $subscriptionsResource->getAll();

    if (!$customerSubscriptions->count()) {
      throw new AcquiaCliException("You have no Cloud subscriptions.");
    }
    return $this->promptChooseFromObjectsOrArrays(
      $customerSubscriptions,
      'uuid',
      'name',
      'Select a Cloud Platform subscription:'
    );
  }

  /**
   * Prompts the user to choose from a list of available Cloud Platform
   * applications.
   */
  private function promptChooseApplication(
    Client $acquiaCloudClient
  ): object|array|null {
    $applicationsResource = new Applications($acquiaCloudClient);
    $customerApplications = $applicationsResource->getAll();

    if (!$customerApplications->count()) {
      throw new AcquiaCliException("You have no Cloud applications.");
    }
    return $this->promptChooseFromObjectsOrArrays(
      $customerApplications,
      'uuid',
      'name',
      'Select a Cloud Platform application:'
    );
  }

  /**
   * Prompts the user to choose from a list of environments for a given Cloud Platform application.
   */
  private function promptChooseEnvironment(
    Client $acquiaCloudClient,
    string $applicationUuid
  ): object|array|null {
    $environmentResource = new Environments($acquiaCloudClient);
    $environments = $environmentResource->getAll($applicationUuid);
    if (!$environments->count()) {
      throw new AcquiaCliException('There are no environments associated with this application.');
    }
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
    $logs = array_map(static function (mixed $logType, mixed $logLabel): array {
      return [
        'label' => $logLabel,
        'type' => $logType,
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
   * @param string $uniqueProperty The property of the $item that will be used to identify the object.
   */
  public function promptChooseFromObjectsOrArrays(array|ArrayObject $items, string $uniqueProperty, string $labelProperty, string $questionText, bool $multiselect = FALSE): object|array|null {
    $list = [];
    foreach ($items as $item) {
      if (is_array($item)) {
        $list[$item[$uniqueProperty]] = trim($item[$labelProperty]);
      }
      else {
        $list[$item->$uniqueProperty] = trim($item->$labelProperty);
      }
    }
    $labels = array_values($list);
    $default = $multiselect ? NULL : $labels[0];
    $question = new ChoiceQuestion($questionText, $labels, $default);
    $question->setMultiselect($multiselect);
    $choiceId = $this->io->askQuestion($question);
    if (!$multiselect) {
      $identifier = array_search($choiceId, $list, TRUE);
      foreach ($items as $item) {
        if (is_array($item)) {
          if ($item[$uniqueProperty] === $identifier) {
            return $item;
          }
        }
        else if ($item->$uniqueProperty === $identifier) {
          return $item;
        }
      }
    }
    else {
      $chosen = [];
      foreach ($choiceId as $choice) {
        $identifier = array_search($choice, $list, TRUE);
        foreach ($items as $item) {
          if (is_array($item)) {
            if ($item[$uniqueProperty] === $identifier) {
              $chosen[] = $item;
            }
          }
          else if ($item->$uniqueProperty === $identifier) {
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
   * @return array<mixed>|null
   */
  private function getGitConfig(): ?array {
    $filePath = $this->projectDir . '/.git/config';
    if (file_exists($filePath)) {
      return parse_ini_file($filePath, TRUE);
    }

    return NULL;
  }

  /**
   * Gets an array of git remotes from a .git/config array.
   *
   * @param array $gitConfig
   * @return array<mixed>
   *   A flat array of git remote urls.
   */
  private function getGitRemotes(array $gitConfig): array {
    $localVcsRemotes = [];
    foreach ($gitConfig as $sectionName => $section) {
      if ((str_contains($sectionName, 'remote ')) &&
        (strpos($section['url'], 'acquia.com') || strpos($section['url'], 'acquia-sites.com'))
      ) {
        $localVcsRemotes[] = $section['url'];
      }
    }

    return $localVcsRemotes;
  }

  private function findCloudApplicationByGitUrl(
        Client $acquiaCloudClient,
        array $localGitRemotes
    ): ?ApplicationResponse {

    // Set up API resources.
    $applicationsResource = new Applications($acquiaCloudClient);
    $customerApplications = $applicationsResource->getAll();
    $environmentsResource = new Environments($acquiaCloudClient);

    // Create progress bar.
    $count = count($customerApplications);
    $progressBar = new ProgressBar($this->output, $count);
    $progressBar->setFormat('message');
    $progressBar->setMessage("Searching <options=bold>$count applications</> on the Cloud Platform...");
    $progressBar->start();

    // Search Cloud applications.
    $terminalWidth = (new Terminal())->getWidth();
    foreach ($customerApplications as $application) {
      // Ensure that the message takes up the full terminal width to prevent display artifacts.
      $message = "Searching <options=bold>{$application->name}</> for matching git URLs";
      $suffixLength = $terminalWidth - strlen($message) - 17;
      $suffix = $suffixLength > 0 ? str_repeat(' ', $suffixLength) : '';
      $progressBar->setMessage($message . $suffix);
      $applicationEnvironments = $environmentsResource->getAll($application->uuid);
      if ($application = $this->searchApplicationEnvironmentsForGitUrl(
            $application,
            $applicationEnvironments,
            $localGitRemotes
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

  protected function createTable(OutputInterface $output, string $title, array $headers, mixed $widths): Table {
    $terminalWidth = (new Terminal())->getWidth();
    $terminalWidth *= .90;
    $table = new Table($output);
    $table->setHeaders($headers);
    $table->setHeaderTitle($title);
    $setWidths = static function (mixed $width) use ($terminalWidth) {
      return (int) ($terminalWidth * $width);
    };
    $table->setColumnWidths(array_map($setWidths, $widths));
    return $table;
  }

  private function searchApplicationEnvironmentsForGitUrl(
    ApplicationResponse $application,
    EnvironmentsResponse $applicationEnvironments,
    array $localGitRemotes
    ): ?ApplicationResponse {
    foreach ($applicationEnvironments as $environment) {
      if ($environment->flags->production && in_array($environment->vcs->url, $localGitRemotes, TRUE)) {
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
    Client $acquiaCloudClient
    ): ?ApplicationResponse {
    if ($this->projectDir && $this->input->isInteractive()) {
      $this->output->writeln("There is no Cloud Platform application linked to <options=bold>{$this->projectDir}/.git</>.");
      $answer = $this->io->confirm('Would you like Acquia CLI to search for a Cloud application that matches your local git config?');
      if ($answer) {
        $this->output->writeln('Searching for a matching Cloud application...');
        if ($gitConfig = $this->getGitConfig()) {
          $localGitRemotes = $this->getGitRemotes($gitConfig);
          if ($cloudApplication = $this->findCloudApplicationByGitUrl($acquiaCloudClient,
            $localGitRemotes)) {
            $this->output->writeln('<info>Found a matching application!</info>');
            return $cloudApplication;
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
   * @return string The environment UUID.
   */
  protected function determineCloudEnvironment(): string {
    if ($this->input->hasArgument('environmentId') && $this->input->getArgument('environmentId')) {
      return $this->input->getArgument('environmentId');
    }

    if (!$this->input->isInteractive()) {
      throw new RuntimeException('Not enough arguments (missing: "environmentId").');
    }

    $applicationUuid = $this->determineCloudApplication();
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    /** @var EnvironmentResponse $environment */
    $environment = $this->promptChooseEnvironment($acquiaCloudClient, $applicationUuid);

    return $environment->uuid;
  }

  /**
   * @return array<mixed>
   */
  protected function getSubscriptionApplications(Client $client, SubscriptionResponse $subscription): array {
    $applicationsResource = new Applications($client);
    $applications = $applicationsResource->getAll();
    $subscriptionApplications = [];

    foreach ($applications as $application) {
      if ($application->subscription->uuid === $subscription->uuid) {
        $subscriptionApplications[] = $application;
      }
    }
    if (count($subscriptionApplications) === 0) {
      throw new AcquiaCliException("You do not have access to any applications on the $subscription->name subscription");
    }
    return $subscriptionApplications;
  }

  protected function determineCloudSubscription(): SubscriptionResponse {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();

    if ($this->input->hasArgument('subscriptionUuid') && $this->input->getArgument('subscriptionUuid')) {
      $cloudSubscriptionUuid = $this->input->getArgument('subscriptionUuid');
      self::validateUuid($cloudSubscriptionUuid);
      return (new Subscriptions($acquiaCloudClient))->get($cloudSubscriptionUuid);
    }

    // Finally, just ask.
    if ($this->input->isInteractive() && $subscription = $this->promptChooseSubscription($acquiaCloudClient)) {
      return $subscription;
    }

    throw new AcquiaCliException("Could not determine Cloud subscription. Run this command interactively or use the `subscriptionUuid` argument.");
  }

  /**
   * Determine the Cloud application.
   */
  protected function determineCloudApplication(bool $promptLinkApp = FALSE): ?string {
    $applicationUuid = $this->doDetermineCloudApplication();
    if (!isset($applicationUuid)) {
      throw new AcquiaCliException("Could not determine Cloud Application. Run this command interactively or use `acli link` to link a Cloud Application before running non-interactively.");
    }

    $application = $this->getCloudApplication($applicationUuid);
    // No point in trying to link a directory that's not a repo.
    if (!empty($this->projectDir) && !$this->getCloudUuidFromDatastore()) {
      if ($promptLinkApp) {
        $this->saveCloudUuidToDatastore($application);
      }
      elseif (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !$this->getCloudApplicationUuidFromBltYaml()) {
        $this->promptLinkApplication($application);
      }
    }

    return $applicationUuid;
  }

  protected function doDetermineCloudApplication(): mixed {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();

    if ($this->input->hasArgument('applicationUuid') && $this->input->getArgument('applicationUuid')) {
      $cloudApplicationUuid = $this->input->getArgument('applicationUuid');
      return self::validateUuid($cloudApplicationUuid);
    }

    // Try local project info.
    if ($applicationUuid = $this->getCloudUuidFromDatastore()) {
      $this->logger->debug("Using Cloud application UUID: $applicationUuid from {$this->datastoreAcli->filepath}");
      return $applicationUuid;
    }

    if ($applicationUuid = $this->getCloudApplicationUuidFromBltYaml()) {
      $this->logger->debug("Using Cloud application UUID $applicationUuid from blt/blt.yml");
      return $applicationUuid;
    }

    // Get from the Cloud Platform env var.
    if ($applicationUuid = self::getThisCloudIdeCloudAppUuid()) {
      return $applicationUuid;
    }

    // Try to guess based on local git url config.
    if ($cloudApplication = $this->inferCloudAppFromLocalGitConfig($acquiaCloudClient)) {
      return $cloudApplication->uuid;
    }

    if ($this->input->isInteractive()) {
      /** @var ApplicationResponse $application */
      $application = $this->promptChooseApplication($acquiaCloudClient);
      if ($application) {
        return $application->uuid;
      }
    }

    return NULL;
  }

  protected function getCloudApplicationUuidFromBltYaml(): ?string {
    $bltYamlFilePath = Path::join($this->projectDir, 'blt', 'blt.yml');
    if (file_exists($bltYamlFilePath)) {
      $contents = Yaml::parseFile($bltYamlFilePath);
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

  private function saveCloudUuidToDatastore(ApplicationResponse $application): bool {
    $this->datastoreAcli->set('cloud_app_uuid', $application->uuid);
    $this->io->success("The Cloud application {$application->name} has been linked to this repository by writing to {$this->datastoreAcli->filepath}");

    return TRUE;
  }

  protected function getCloudUuidFromDatastore(): mixed {
    return $this->datastoreAcli->get('cloud_app_uuid');
  }

  private function promptLinkApplication(
    ?ApplicationResponse $cloudApplication
    ): bool {
    $answer = $this->io->confirm("Would you like to link the Cloud application <bg=cyan;options=bold>{$cloudApplication->name}</> to this repository?");
    if ($answer) {
      return $this->saveCloudUuidToDatastore($cloudApplication);
    }
    return FALSE;
  }

  protected function validateCwdIsValidDrupalProject(): void {
    if (!$this->projectDir) {
      throw new AcquiaCliException('Could not find a local Drupal project. Looked for `docroot/index.php` in current and parent directories. Execute this command from within a Drupal project directory.');
    }
  }

  /**
   * Determines if Acquia CLI is being run from within a Cloud IDE.
   *
   * @return bool TRUE if Acquia CLI is being run from within a Cloud IDE.
   */
  public static function isAcquiaCloudIde(): bool {
    return AcquiaDrupalEnvironmentDetector::isAhIdeEnv();
  }

  /**
   * Get the Cloud Application UUID from a Cloud IDE's environmental variable.
   *
   * This command assumes it is being run inside a Cloud IDE.
   *
   * @return array<string>|false|string
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

  protected function getCloudApplication(string $applicationUuid): ApplicationResponse {
    $applicationsResource = new Applications($this->cloudApiClientService->getClient());
    return $applicationsResource->get($applicationUuid);
  }

  protected function getCloudEnvironment(string $environmentId): EnvironmentResponse {
    $environmentResource = new Environments($this->cloudApiClientService->getClient());

    return $environmentResource->get($environmentId);
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

  protected function getEnvironmentFromAliasArg(string $alias): EnvironmentResponse {
    return $this->getEnvFromAlias($alias);
  }

  private function getEnvFromAlias(string $alias): EnvironmentResponse {
    return self::getAliasCache()->get($alias, function () use ($alias): \AcquiaCloudApi\Response\EnvironmentResponse {
      return $this->doGetEnvFromAlias($alias);
    });
  }

  private function doGetEnvFromAlias(string $alias): EnvironmentResponse {
    $siteEnvParts = explode('.', $alias);
    [$applicationAlias, $environmentAlias] = $siteEnvParts;
    $this->logger->debug("Searching for an environment matching alias $applicationAlias.$environmentAlias.");
    $customerApplication = $this->getApplicationFromAlias($applicationAlias);
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $environmentsResource = new Environments($acquiaCloudClient);
    $environments = $environmentsResource->getAll($customerApplication->uuid);
    foreach ($environments as $environment) {
      if ($environment->name === $environmentAlias) {
        $this->logger->debug("Found environment {$environment->uuid} matching $environmentAlias.");

        return $environment;
      }
    }

    throw new AcquiaCliException("Environment not found matching the alias {alias}", ['alias' => "$applicationAlias.$environmentAlias"]);
  }

  private function getApplicationFromAlias(string $applicationAlias): mixed {
    return self::getAliasCache()
      ->get($applicationAlias, function () use ($applicationAlias) {
        return $this->doGetApplicationFromAlias($applicationAlias);
      });
  }

  /**
   * Return the ACLI alias cache.
   */
  public static function getAliasCache(): AliasCache {
    return new AliasCache('acli_aliases');
  }

  private function doGetApplicationFromAlias(string $applicationAlias): mixed {
    if (!strpos($applicationAlias, ':')) {
      $applicationAlias = '*:' . $applicationAlias;
    }
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    // No need to clear this query later since getClient() is a factory method.
    $acquiaCloudClient->addQuery('filter', 'hosting=@' . $applicationAlias);
    // Allow Cloud Platform users with 'support' role to resolve aliases for applications to
    // which they don't explicitly belong.
    $accountResource = new Account($acquiaCloudClient);
    $account = $accountResource->get();
    if ($account->flags->support) {
      $acquiaCloudClient->addQuery('all', 'true');
    }
    $applicationsResource = new Applications($acquiaCloudClient);
    $customerApplications = $applicationsResource->getAll();
    if (count($customerApplications) === 0) {
      throw new AcquiaCliException("No applications match the alias {applicationAlias}", ['applicationAlias' => $applicationAlias]);
    }
    if (count($customerApplications) > 1) {
      $callback = static function (mixed $element) {
        return $element->hosting->id;
      };
      $aliases = array_map($callback, (array) $customerApplications);
      $this->io->error(sprintf("Use a unique application alias: %s", implode(', ', $aliases)));
      throw new AcquiaCliException("Multiple applications match the alias {applicationAlias}", ['applicationAlias' => $applicationAlias]);
    }

    $customerApplication = $customerApplications[0];

    $this->logger->debug("Found application {$customerApplication->uuid} matching alias $applicationAlias.");

    return $customerApplication;
  }

  protected function requireCloudIdeEnvironment(): void {
    if (!self::isAcquiaCloudIde() || !self::getThisCloudIdeUuid()) {
      throw new AcquiaCliException('This command can only be run inside of an Acquia Cloud IDE');
    }
  }

  /**
   * @return \stdClass|null
   */
  protected function findIdeSshKeyOnCloud(string $ideUuid): ?stdClass {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $cloudKeys = $acquiaCloudClient->request('get', '/account/ssh-keys');
    $idesResource = new Ides($acquiaCloudClient);
    $ide = $idesResource->get($ideUuid);
    $sshKeyLabel = SshKeyCommandBase::getIdeSshKeyLabel($ide);
    foreach ($cloudKeys as $cloudKey) {
      if ($cloudKey->label === $sshKeyLabel) {
        return $cloudKey;
      }
    }
    return NULL;
  }

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

    $releases = json_decode((string) $response->getBody(), FALSE, 512, JSON_THROW_ON_ERROR);
    if (!isset($releases[0])) {
      $this->logger->debug('No releases found at GitHub repository acquia/cli');
      return FALSE;
    }

    foreach ($releases as $release) {
      if (!$release->prerelease) {
        /**
         * @var string $version
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

  protected function fillMissingRequiredApplicationUuid(InputInterface $input, OutputInterface $output): void {
    if ($input->hasArgument('applicationUuid') && !$input->getArgument('applicationUuid') && $this->getDefinition()->getArgument('applicationUuid')->isRequired()) {
      $output->writeln('Inferring Cloud Application UUID for this command since none was provided...', OutputInterface::VERBOSITY_VERBOSE);
      if ($applicationUuid = $this->determineCloudApplication()) {
        $output->writeln("Set application uuid to <options=bold>$applicationUuid</>", OutputInterface::VERBOSITY_VERBOSE);
        $input->setArgument('applicationUuid', $applicationUuid);
      }
    }
  }

  private function convertUserAliasToUuid(InputInterface $input, string $userUuidArgument, string $orgUuidArgument): void {
    if ($input->hasArgument($userUuidArgument)
      && $input->getArgument($userUuidArgument)
      && $input->hasArgument($orgUuidArgument)
      && $input->getArgument($orgUuidArgument)
    ) {
      $userUuID = $input->getArgument($userUuidArgument);
      $orgUuid = $input->getArgument($orgUuidArgument);
      $userUuid = $this->validateUserUuid($userUuID, $orgUuid);
      $input->setArgument($userUuidArgument, $userUuid);
    }
  }

  /**
   * @param string $userUuidArgument User alias like uuid or email.
   * @param string $orgUuidArgument Organization uuid.
   * @return string User uuid from alias
   */
  private function validateUserUuid(string $userUuidArgument, string $orgUuidArgument): string {
    try {
      self::validateUuid($userUuidArgument);
    }
    catch (ValidatorException) {
      // Since this isn't a valid UUID, assuming this is email address.
      return $this->getUserUuidFromUserAlias($userUuidArgument, $orgUuidArgument);
    }

    return $userUuidArgument;
  }

  /**
   * @param String $userAlias User alias like uuid or email.
   * @param String $orgUuidArgument Organization uuid.
   * @return string User uuid from alias
   */
  private function getUserUuidFromUserAlias(string $userAlias, string $orgUuidArgument): string {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $organizationResource = new Organizations($acquiaCloudClient);
    $orgMembers = $organizationResource->getMembers($orgUuidArgument);

    // If there are no members.
    if (count($orgMembers) === 0) {
      throw new AcquiaCliException('Organization has no members');
    }

    foreach ($orgMembers as $member) {
      // If email matches with any member.
      if ($member->mail === $userAlias) {
        return $member->uuid;
      }
    }

    throw new AcquiaCliException('No matching user found in this organization');
  }

  protected function convertApplicationAliasToUuid(InputInterface $input): void {
    if ($input->hasArgument('applicationUuid') && $input->getArgument('applicationUuid')) {
      $applicationUuidArgument = $input->getArgument('applicationUuid');
      $applicationUuid = $this->validateApplicationUuid($applicationUuidArgument);
      $input->setArgument('applicationUuid', $applicationUuid);
    }
  }

  protected function convertEnvironmentAliasToUuid(InputInterface $input, mixed $argumentName): void {
    if ($input->hasArgument($argumentName) && $input->getArgument($argumentName)) {
      $envUuidArgument = $input->getArgument($argumentName);
      $environmentUuid = $this->validateEnvironmentUuid($envUuidArgument, $argumentName);
      $input->setArgument($argumentName, $environmentUuid);
    }
  }

  /**
   * @param string $sshUrl The SSH URL to the server.
   * @return string The sitegroup. E.g., eemgrasmick.
   */
  public static function getSiteGroupFromSshUrl(string $sshUrl): string {
    $sshUrlParts = explode('.', $sshUrl);
    return reset($sshUrlParts);
  }

  protected function isAcsfEnv(mixed $cloudEnvironment): bool {
    if (str_contains($cloudEnvironment->sshUrl, 'enterprise-g1')) {
      return TRUE;
    }
    foreach ($cloudEnvironment->domains as $domain) {
      if (str_contains($domain, 'acsitefactory')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * @return array<mixed>
   */
  protected function getAcsfSites(EnvironmentResponse $cloudEnvironment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloudEnvironment->sshUrl);
    $command = ['cat', "/var/www/site-php/$sitegroup.{$cloudEnvironment->name}/multisite-config.json"];
    $process = $this->sshHelper->executeCommand($cloudEnvironment, $command, FALSE);
    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    throw new AcquiaCliException("Could not get ACSF sites");
  }

  /**
   * @return array<mixed>
   */
  private function getCloudSites(EnvironmentResponse $cloudEnvironment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloudEnvironment->sshUrl);
    $command = ['ls', $this->getCloudSitesPath($cloudEnvironment, $sitegroup)];
    $process = $this->sshHelper->executeCommand($cloudEnvironment, $command, FALSE);
    $sites = array_filter(explode("\n", trim($process->getOutput())));
    if ($process->isSuccessful() && $sites) {
      return $sites;
    }

    throw new AcquiaCliException("Could not get Cloud sites for " . $cloudEnvironment->name);
  }

  protected function getCloudSitesPath(mixed $cloudEnvironment, mixed $sitegroup): string {
    if ($cloudEnvironment->platform === 'cloud-next') {
      $path = "/home/clouduser/{$cloudEnvironment->name}/sites";
    }
    else {
      $path = "/mnt/files/$sitegroup.{$cloudEnvironment->name}/sites";
    }
    return $path;
  }

  protected function promptChooseAcsfSite(EnvironmentResponse $cloudEnvironment): mixed {
    $choices = [];
    $acsfSites = $this->getAcsfSites($cloudEnvironment);
    foreach ($acsfSites['sites'] as $domain => $acsfSite) {
      $choices[] = "{$acsfSite['name']} ($domain)";
    }
    $choice = $this->io->choice('Choose a site', $choices, $choices[0]);
    $key = array_search($choice, $choices, TRUE);
    $sites = array_values($acsfSites['sites']);
    $site = $sites[$key];

    return $site['name'];
  }

  protected function promptChooseCloudSite(EnvironmentResponse $cloudEnvironment): mixed {
    $sites = $this->getCloudSites($cloudEnvironment);
    if (count($sites) === 1) {
      $site = reset($sites);
      $this->logger->debug("Only a single Cloud site was detected. Assuming site is $site");
      return $site;
    }
    $this->logger->debug("Multisite detected");
    $this->warnMultisite();
    return $this->io->choice('Choose a site', $sites, $sites[0]);
  }

  protected static function isLandoEnv(): bool {
    return AcquiaDrupalEnvironmentDetector::isLandoEnv();
  }

  protected function reAuthenticate(string $apiKey, string $apiSecret, ?string $baseUri, ?string $accountsUri): void {
    // Client service needs to be reinitialized with new credentials in case
    // this is being run as a sub-command.
    // @see https://github.com/acquia/cli/issues/403
    $this->cloudApiClientService->setConnector(new Connector([
      'key' => $apiKey,
      'secret' => $apiSecret,
    ], $baseUri, $accountsUri));
  }

  private function warnMultisite(): void {
    $this->io->note("This is a multisite application. Drupal will load the default site unless you've configured sites.php for this environment: https://docs.acquia.com/cloud-platform/develop/drupal/multisite/");
  }

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
    return static function (mixed $type, mixed $buffer) use ($checklist, $output): void {
      if (!$output->isVerbose() && $checklist->getItems()) {
        $checklist->updateProgressBar($buffer);
      }
      $output->writeln($buffer, OutputInterface::VERBOSITY_VERY_VERBOSE);
    };
  }

  protected function getDrushDatabaseConnectionStatus(Closure $outputCallback = NULL): bool {
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
      ], $outputCallback, $this->dir, FALSE);
      if ($process->isSuccessful()) {
        $drushStatusReturnOutput = json_decode($process->getOutput(), TRUE, 512);
        if (is_array($drushStatusReturnOutput) && array_key_exists('db-status', $drushStatusReturnOutput) && $drushStatusReturnOutput['db-status'] === 'Connected') {
          $this->drushHasActiveDatabaseConnection = TRUE;
          return $this->drushHasActiveDatabaseConnection;
        }
      }
    }

    $this->drushHasActiveDatabaseConnection = FALSE;

    return $this->drushHasActiveDatabaseConnection;
  }

  protected function createMySqlDumpOnLocal(string $dbHost, string $dbUser, string $dbName, string $dbPassword, Closure $outputCallback = NULL): string {
    $this->localMachineHelper->checkRequiredBinariesExist(['mysqldump', 'gzip']);
    $filename = "acli-mysql-dump-{$dbName}.sql.gz";
    $localTempDir = sys_get_temp_dir();
    $localFilepath = $localTempDir . '/' . $filename;
    $this->logger->debug("Dumping MySQL database to $localFilepath on this machine");
    $this->localMachineHelper->checkRequiredBinariesExist(['mysqldump', 'gzip']);
    if ($outputCallback) {
      $outputCallback('out', "Dumping MySQL database to $localFilepath on this machine");
    }
    if ($this->localMachineHelper->commandExists('pv')) {
      $command = "MYSQL_PWD={$dbPassword} mysqldump --host={$dbHost} --user={$dbUser} {$dbName} | pv --rate --bytes | gzip -9 > $localFilepath";
    }
    else {
      $this->io->warning('Install `pv` to see progress bar');
      $command = "MYSQL_PWD={$dbPassword} mysqldump --host={$dbHost} --user={$dbUser} {$dbName} | gzip -9 > $localFilepath";
    }

    $process = $this->localMachineHelper->executeFromCmd($command, $outputCallback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful() || $process->getOutput()) {
      throw new AcquiaCliException('Unable to create a dump of the local database. {message}', ['message' => $process->getErrorOutput()]);
    }

    return $localFilepath;
  }

  protected function promptOpenBrowserToCreateToken(
    InputInterface $input
  ): void {
    if (!$input->getOption('key') || !$input->getOption('secret')) {
      $tokenUrl = 'https://cloud.acquia.com/a/profile/tokens';
      $this->output->writeln("You will need a Cloud Platform API token from <href=$tokenUrl>$tokenUrl</>");

      if (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && $this->io->confirm('Do you want to open this page to generate a token now?')) {
        $this->localMachineHelper->startBrowser($tokenUrl);
      }
    }
  }

  protected function determineApiKey(): string {
    return $this->determineOption('key', FALSE, Closure::fromCallable([$this, 'validateApiKey']));
  }

  private function validateApiKey(mixed $key): string {
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

  protected function determineApiSecret(): string {
    return $this->determineOption('secret', TRUE, Closure::fromCallable([$this, 'validateApiKey']));
  }

  /**
   * Get an option, either passed explicitly or via interactive prompt.
   *
   * Default can be passed explicitly, separately from the option default,
   * because Symfony does not make a distinction between an option value set
   * explicitly or by default. In other words, we can't prompt for the value of
   * an option that already has a default value.
   */
  protected function determineOption(string $optionName, bool $hidden = FALSE, ?Closure $validator = NULL, ?Closure $normalizer = NULL, ?string $default = NULL): string|int|null {
    if ($optionValue = $this->input->getOption($optionName)) {
      if (isset($normalizer)) {
        $optionValue = $normalizer($optionValue);
      }
      if (isset($validator)) {
        $validator($optionValue);
      }
      return $optionValue;
    }
    $option = $this->getDefinition()->getOption($optionName);
    $optionShortcut = $option->getShortcut();
    $description = lcfirst($option->getDescription());
    if ($optionShortcut) {
      $message = "Enter $description (option <options=bold>-$optionShortcut</>, <options=bold>--$optionName</>)";
    }
    else {
      $message = "Enter $description (option <options=bold>--$optionName</>)";
    }
    $optional = $option->isValueOptional();
    $message .= $optional ? ' (optional)' : '';
    $message .= $hidden ? ' (input will be hidden)' : '';
    $question = new Question($message, $default);
    $question->setHidden($this->localMachineHelper->useTty() && $hidden);
    $question->setHiddenFallback($hidden);
    if (isset($normalizer)) {
      $question->setNormalizer($normalizer);
    }
    if (isset($validator)) {
      $question->setValidator($validator);
    }
    $optionValue = $this->io->askQuestion($question);
    // Question bypasses validation if session is non-interactive.
    if (!$optional && is_null($optionValue)) {
      throw new AcquiaCliException($message);
    }
    return $optionValue;
  }

  /**
   * Get the first environment for a given Cloud application matching a filter.
   */
  private function getAnyAhEnvironment(string $cloudAppUuid, callable $filter): EnvironmentResponse|false {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $environmentResource = new Environments($acquiaCloudClient);
    /** @var EnvironmentResponse[] $applicationEnvironments */
    $applicationEnvironments = iterator_to_array($environmentResource->getAll($cloudAppUuid));
    $candidates = array_filter($applicationEnvironments, $filter);
    return reset($candidates);
  }

  /**
   * Get the first non-prod environment for a given Cloud application.
   */
  protected function getAnyNonProdAhEnvironment(string $cloudAppUuid): EnvironmentResponse|false {
    return $this->getAnyAhEnvironment($cloudAppUuid, function (mixed $environment) {
      return !$environment->flags->production;
    });
  }

  /**
   * Get the first prod environment for a given Cloud application.
   */
  protected function getAnyProdAhEnvironment(string $cloudAppUuid): EnvironmentResponse|false {
    return $this->getAnyAhEnvironment($cloudAppUuid, function (mixed $environment) {
      return $environment->flags->production;
    });
  }

  /**
   * Get the first VCS URL for a given Cloud application.
   */
  protected function getAnyVcsUrl(string $cloudAppUuid): string {
    $environment = $this->getAnyAhEnvironment($cloudAppUuid, function (): bool {
      return TRUE;
    });
    return $environment->vcs->url;
  }

  protected function validateApplicationUuid(string $applicationUuidArgument): mixed {
    try {
      self::validateUuid($applicationUuidArgument);
    }
    catch (ValidatorException) {
      // Since this isn't a valid UUID, let's see if it's a valid alias.
      $alias = $this->normalizeAlias($applicationUuidArgument);
      return $this->getApplicationFromAlias($alias)->uuid;
    }
    return $applicationUuidArgument;
  }

  protected function validateEnvironmentUuid(mixed $envUuidArgument, mixed $argumentName): string {
    if (is_null($envUuidArgument)) {
      throw new AcquiaCliException("{{$argumentName}} must not be null");
    }
    try {
      // Environment IDs take the form of [env-num]-[app-uuid].
      $uuidParts = explode('-', $envUuidArgument);
      unset($uuidParts[0]);
      $applicationUuid = implode('-', $uuidParts);
      self::validateUuid($applicationUuid);
    }
    catch (ValidatorException) {
      try {
        // Since this isn't a valid environment ID, let's see if it's a valid alias.
        $alias = $envUuidArgument;
        $alias = $this->normalizeAlias($alias);
        $alias = self::validateEnvironmentAlias($alias);
        return $this->getEnvironmentFromAliasArg($alias)->uuid;
      }
      catch (AcquiaCliException) {
        throw new AcquiaCliException("{{$argumentName}} must be a valid UUID or site alias.");
      }
    }
    return $envUuidArgument;
  }

  protected function checkAuthentication(): void {
    if ($this->commandRequiresAuthentication() && !$this->cloudApiClientService->isMachineAuthenticated()) {
      throw new AcquiaCliException('This machine is not yet authenticated with the Cloud Platform. Run `acli auth:login`');
    }
  }

  protected function waitForNotificationToComplete(Client $acquiaCloudClient, string $uuid, string $message, callable $success = NULL): bool {
    $notificationsResource = new Notifications($acquiaCloudClient);
    $notification = NULL;
    $checkNotificationStatus = static function () use ($notificationsResource, &$notification, $uuid): bool {
      $notification = $notificationsResource->get($uuid);
      return $notification->status !== 'in-progress';
    };
    if ($success === NULL) {
      $success = function () use (&$notification): void {
        $this->writeCompletedMessage($notification);
      };
    }
    LoopHelper::getLoopy($this->output, $this->io, $this->logger, $message, $checkNotificationStatus, $success);
    return $notification->status === 'completed';
  }

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
    $completedAt = date("D M j G:i:s T Y", strtotime($notification->completed_at));
    $this->io->writeln("Progress: {$notification->progress}");
    $this->io->writeln("Completed: $completedAt");
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
    $notificationUrl = $links->notification->href;
    $urlParts = explode('/', $notificationUrl);
    return $urlParts[5];
  }

  protected function validateRequiredCloudPermissions(Client $acquiaCloudClient, ?string $cloudApplicationUuid, AccountResponse $account, array $requiredPermissions): void {
    $permissions = $acquiaCloudClient->request('get', "/applications/{$cloudApplicationUuid}/permissions");
    $keyedPermissions = [];
    foreach ($permissions as $permission) {
      $keyedPermissions[$permission->name] = $permission;
    }
    foreach ($requiredPermissions as $name) {
      if (!array_key_exists($name, $keyedPermissions)) {
        throw new AcquiaCliException("The Acquia Cloud Platform account {account} does not have the required '{name}' permission. Add the permissions to this user or use an API Token belonging to a different Acquia Cloud Platform user.", [
          'account' => $account->mail,
          'name' => $name,
        ]);
      }
    }
  }

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
