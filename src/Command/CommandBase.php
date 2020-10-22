<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\DataStore\YamlStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\ClientService;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Helpers\UpdateHelper;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Endpoints\Logs;
use AcquiaCloudApi\Response\ApplicationResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaLogstream\LogstreamManager;
use ArrayObject;
use drupol\phposinfo\OsInfo;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\ItemInterface;
use Webmozart\KeyValueStore\JsonFileStore;
use Webmozart\PathUtil\Path;
use Zumba\Amplitude\Amplitude;

/**
 * Class CommandBase.
 *
 * @package Grasmash\YamlCli\Command
 */
abstract class CommandBase extends Command implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * @var \Symfony\Component\Console\Style\SymfonyStyle
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
   * @var \Symfony\Component\Console\Helper\QuestionHelper
   */
  protected $questionHelper;

  /**
   * @var \Acquia\Cli\Helpers\TelemetryHelper
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
   * @var \Acquia\Cli\DataStore\YamlStore
   */
  protected $datastoreAcli;

  /**
   * @var string
   */
  protected $cloudConfigFilepath;

  /**
   * @var string
   */
  protected $acliConfigFilepath;

  /**
   * @var \Zumba\Amplitude\Amplitude
   */
  protected $amplitude;

  protected $repoRoot;

  /**
   * @var \Acquia\Cli\Helpers\ClientService
   */
  protected $cloudApiClientService;

  /**
   * @var \AcquiaLogstream\LogstreamManager
   */
  protected $logstreamManager;

  /**
   * @var \Acquia\Cli\Helpers\SshHelper
   */
  public $sshHelper;

  /**
   * @var string
   */
  protected $sshDir;

  /**
   * @var \Acquia\Cli\Helpers\UpdateHelper
   */
  protected $updateHelper;

  protected $localDbUser;
  protected $localDbPassword;
  protected $localDbName;
  protected $localDbHost;

  /**
   * CommandBase constructor.
   *
   * @param string $cloudConfigFilepath
   * @param \Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   * @param \Acquia\Cli\Helpers\UpdateHelper $updateHelper
   * @param \Webmozart\KeyValueStore\JsonFileStore $datastoreCloud
   * @param \Acquia\Cli\DataStore\YamlStore $datastoreAcli
   * @param \Acquia\Cli\Helpers\TelemetryHelper $telemetryHelper
   * @param \Zumba\Amplitude\Amplitude $amplitude
   * @param string $acliConfigFilepath
   * @param string $repoRoot
   * @param \Acquia\Cli\Helpers\ClientService $cloudApiClientService
   * @param \AcquiaLogstream\LogstreamManager $logstreamManager
   * @param \Acquia\Cli\Helpers\SshHelper $sshHelper
   * @param string $sshDir
   */
  public function __construct(
    string $cloudConfigFilepath,
    LocalMachineHelper $localMachineHelper,
    UpdateHelper $updateHelper,
    JsonFileStore $datastoreCloud,
    YamlStore $datastoreAcli,
    TelemetryHelper $telemetryHelper,
    Amplitude $amplitude,
    string $acliConfigFilepath,
    string $repoRoot,
    ClientService $cloudApiClientService,
    LogstreamManager $logstreamManager,
    SshHelper $sshHelper,
    string $sshDir
  ) {
    $this->cloudConfigFilepath = $cloudConfigFilepath;
    $this->localMachineHelper = $localMachineHelper;
    $this->updateHelper = $updateHelper;
    $this->datastoreCloud = $datastoreCloud;
    $this->datastoreAcli = $datastoreAcli;
    $this->telemetryHelper = $telemetryHelper;
    $this->amplitude = $amplitude;
    $this->acliConfigFilepath = $acliConfigFilepath;
    $this->repoRoot = $repoRoot;
    $this->cloudApiClientService = $cloudApiClientService;
    $this->logstreamManager = $logstreamManager;
    $this->sshHelper = $sshHelper;
    $this->sshDir = $sshDir;
    parent::__construct();
  }

  /**
   * @param string $repoRoot
   */
  public function setRepoRoot(string $repoRoot): void {
    $this->repoRoot = $repoRoot;
  }

  public function getLocalDbUser() {
    if (!isset($this->localDbUser)) {
      $this->localDbUser = 'drupal';
      if ($lando_info = self::getLandoInfo()) {
        $this->localDbUser = $lando_info->database->creds->user;
      }
    }

    return $this->localDbUser;
  }

  /**
   * @return mixed
   */
  public function getLocalDbPassword() {
    if (!isset($this->localDbPassword)) {
      $this->localDbPassword = 'drupal';
      if ($lando_info = self::getLandoInfo()) {
        $this->localDbPassword = $lando_info->database->creds->password;
      }
    }

    return $this->localDbPassword;
  }

  /**
   * @return mixed
   */
  public function getLocalDbName() {
    if (!isset($this->localDbName)) {
      $this->localDbName = 'drupal';
      if ($lando_info = self::getLandoInfo()) {
        $this->localDbName = $lando_info->database->creds->database;
      }
    }

    return $this->localDbName;
  }

  /**
   * @return mixed
   */
  public function getLocalDbHost() {
    if (!isset($this->localDbHost)) {
      $this->localDbHost = 'localhost';
      if ($lando_info = self::getLandoInfo()) {
        $this->localDbHost = $lando_info->database->hostnames[0];
      }
    }

    return $this->localDbHost;
  }

  /**
   * Initializes the command just after the input has been validated.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   An InputInterface instance.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   An OutputInterface instance.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
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
    // @todo This logger is not shared in the container! Fix that.
    $this->setLogger(new ConsoleLogger($output));

    $this->questionHelper = $this->getHelper('question');
    $this->checkAndPromptTelemetryPreference();
    $this->telemetryHelper->initializeAmplitude($this->amplitude);

    if ($this->commandRequiresAuthentication($this->input) && !self::isMachineAuthenticated($this->datastoreCloud)) {
      throw new AcquiaCliException('This machine is not yet authenticated with the Cloud Platform. Please run `acli auth:login`');
    }

    $this->convertApplicationAliastoUuid($input);
    $this->fillMissingRequiredApplicationUuid($input, $output);
    $this->convertEnvironmentAliasToUuid($input);
    $this->checkForNewVersion($input, $output);
  }

  /**
   * @param \Webmozart\KeyValueStore\JsonFileStore $cloud_datastore
   *
   * @return bool
   */
  public static function isMachineAuthenticated(JsonFileStore $cloud_datastore): bool {
    return $cloud_datastore !== NULL && $cloud_datastore->get('key') && $cloud_datastore->get('secret');
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
    if ((!isset($send_telemetry) || is_null($send_telemetry)) && $this->input->isInteractive()) {
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
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
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
    $this->amplitude->queueEvent('Ran command', $event_properties);

    return $exit_code;
  }

  /**
   * Add argument and usage examples for applicationUuid.
   */
  protected function acceptApplicationUuid() {
    $this->addArgument('applicationUuid', InputArgument::OPTIONAL, 'The Cloud Platform application UUID or alias.')
    ->addUsage(self::getDefaultName() . ' [<applicationAlias>]')
    ->addUsage(self::getDefaultName() . ' myapp')
    ->addUsage(self::getDefaultName() . ' abcd1234-1111-2222-3333-0e02b2c3d470');
  }

  /**
   * Add argument and usage examples for environmentId.
   */
  protected function acceptEnvironmentId() {
    $this->addArgument('environmentId', InputArgument::OPTIONAL, 'The Cloud Platform environment ID or alias.')
    ->addUsage(self::getDefaultName() . ' [<environmentAlias>]')
    ->addUsage(self::getDefaultName() . ' myapp.dev')
    ->addUsage(self::getDefaultName() . ' 12345-abcd1234-1111-2222-3333-0e02b2c3d470');
  }

  /**
   * Indicates whether the command requires the machine to be authenticated with the Cloud Platform.
   *
   * @param $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    // In fact some other commands such as `api:list` don't require auth, but it's easier and safer to assume they do.
    return $input->getFirstArgument() !== 'auth:login';
  }

  /**
   * Prompts the user to choose from a list of available Cloud Platform applications.
   *
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   *
   * @return null|object|array
   */
  protected function promptChooseApplication(
    Client $acquia_cloud_client
  ) {
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();
    return $this->promptChooseFromObjects(
      $customer_applications,
      'uuid',
      'name',
      'Please select a Cloud Platform application:'
    );
  }

  /**
   * Prompts the user to choose from a list of environments for a given Cloud Platform application.
   *
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
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
    return $this->promptChooseFromObjects(
      $environments,
      'uuid',
      'name',
      'Please select a Cloud Platform environment:'
    );
  }

  /**
   * Prompts the user to choose from a list of logs for a given Cloud Platform environment.
   *
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
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

    return $this->promptChooseFromObjects(
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
   * @param object[]|ArrayObject $items An array of objects.
   * @param string $unique_property The property of the $item that will be used to identify the object.
   * @param string $label_property
   * @param string $question_text
   *
   * @param bool $multiselect
   *
   * @return null|object|array
   */
  public function promptChooseFromObjects($items, $unique_property, $label_property, $question_text, $multiselect = FALSE) {
    $list = [];
    foreach ($items as $item) {
      $list[$item->$unique_property] = trim($item->$label_property);
    }
    $labels = array_values($list);
    $question = new ChoiceQuestion($question_text, $labels);
    $question->setMultiselect($multiselect);
    $helper = $this->getHelper('question');
    $choice_id = $helper->ask($this->input, $this->output, $question);
    if (!$multiselect) {
      $identifier = array_search($choice_id, $list, TRUE);
      foreach ($items as $item) {
        if ($item->$unique_property === $identifier) {
          return $item;
        }
      }
    }
    else {
      $chosen = [];
      foreach ($choice_id as $choice) {
        $identifier = array_search($choice, $list, TRUE);
        foreach ($items as $item) {
          if ($item->$unique_property === $identifier) {
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
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param array $local_git_remotes
   *
   * @return \AcquiaCloudApi\Response\ApplicationResponse|null
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
   * @param \AcquiaCloudApi\Response\ApplicationResponse $application
   * @param \AcquiaCloudApi\Response\EnvironmentsResponse $application_environments
   * @param array $local_git_remotes
   *
   * @return \AcquiaCloudApi\Response\ApplicationResponse|null
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
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   *
   * @return \AcquiaCloudApi\Response\ApplicationResponse|null
   */
  protected function inferCloudAppFromLocalGitConfig(
    Client $acquia_cloud_client
    ): ?ApplicationResponse {
    if ($this->repoRoot) {
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

    $application_uuid = $this->determineCloudApplication();
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environment = $this->promptChooseEnvironment($acquia_cloud_client, $application_uuid);

    return $environment->uuid;
  }

  /**
   * Determine the Cloud application.
   *
   * @param bool $link_app
   *
   * @return string|null
   * @throws \Exception
   */
  protected function determineCloudApplication($link_app = FALSE): ?string {
    $application_uuid = $this->doDetermineCloudApplication();
    if (isset($application_uuid)) {
      $application = $this->getCloudApplication($application_uuid);
      // No point in trying to link a directory that's not a repo.
      if (!empty($this->repoRoot) && !$this->getCloudUuidFromDatastore()) {
        if ($link_app) {
          $this->saveCloudUuidToDatastore($application);
        }
        elseif (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !$this->getCloudApplicationUuidFromBltYaml()) {
          $this->promptLinkApplication($application);
        }
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
      $this->logger->debug("Using Cloud application UUID: $application_uuid from .acquia.yml");
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
    if ($application = $this->promptChooseApplication($acquia_cloud_client)) {
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
   * @return mixed
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
   * @param \AcquiaCloudApi\Response\ApplicationResponse $application
   *
   * @return bool
   * @throws \Exception
   */
  protected function saveCloudUuidToDatastore(ApplicationResponse $application): bool {
    $this->datastoreAcli->set('cloud_app_uuid', $application->uuid);
    $this->io->success("The Cloud application {$application->name} has been linked to this repository");

    return TRUE;
  }

  /**
   * @return mixed
   */
  protected function getCloudUuidFromDatastore() {
    return $this->datastoreAcli->get('cloud_app_uuid');
  }

  /**
   * @param \AcquiaCloudApi\Response\ApplicationResponse|null $cloud_application
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
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
   * This command assumes it is being run inside of a Cloud IDE.
   *
   * @return false|string
   */
  public static function getThisCloudIdeUuid() {
    return getenv('REMOTEIDE_UUID');
  }

  /**
   * @param $application_uuid
   *
   * @return \AcquiaCloudApi\Response\ApplicationResponse
   * @throws \Exception
   */
  protected function getCloudApplication($application_uuid): ApplicationResponse {
    $applications_resource = new Applications($this->cloudApiClientService->getClient());

    return $applications_resource->get($application_uuid);
  }

  /**
   * @param $environment_id
   *
   * @return \AcquiaCloudApi\Response\EnvironmentResponse
   * @throws \Exception
   */
  protected function getCloudEnvironment($environment_id): EnvironmentResponse {
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
   * @return \AcquiaCloudApi\Response\EnvironmentResponse
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getEnvironmentFromAliasArg($alias): EnvironmentResponse {
    return $this->getEnvFromAlias($alias);
  }

  /**
   * @param $alias
   *
   * @return \AcquiaCloudApi\Response\EnvironmentResponse
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
   * @return \AcquiaCloudApi\Response\EnvironmentResponse
   * @throws \Acquia\Cli\Exception\AcquiaCliException
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
   * @return \Symfony\Component\Cache\Adapter\FilesystemAdapter
   */
  public static function getAliasCache() {
    return new FilesystemAdapter('acli_aliases');
  }

  /**
   * @param $application_alias
   *
   * @return mixed
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function doGetApplicationFromAlias($application_alias) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $acquia_cloud_client->addQuery('filter', 'hosting=@*' . $application_alias);
    $customer_applications = $acquia_cloud_client->request('get', '/applications');
    $site_prefix = '';
    if ($customer_applications) {
      $customer_application = $customer_applications[0];
      $site_id = $customer_application->hosting->id;
      $parts = explode(':', $site_id);
      $site_prefix = $parts[1];
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function requireCloudIdeEnvironment(): void {
    if (!self::isAcquiaCloudIde()) {
      throw new AcquiaCliException('This command can only be run inside of an Acquia Cloud IDE');
    }
  }

  /**
   * @param string $ide_uuid
   *
   * @return \stdClass|null
   */
  protected function findIdeSshKeyOnCloud($ide_uuid): ?\stdClass {
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function deleteSshKeyFromCloud(\stdClass $cloud_key): void {
    $return_code = $this->executeAcliCommand('ssh-key:delete', [
      '--cloud-key-uuid' => $cloud_key->uuid,
    ]);
    if ($return_code !== 0) {
      throw new AcquiaCliException('Unable to delete SSH key from the Cloud Platform');
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function checkForNewVersion(InputInterface $input, OutputInterface $output): void {
    try {
      $updater = $this->updateHelper->getUpdater($input, $output, $this->getApplication());
      if (strpos($input->getArgument('command'), 'api:') === FALSE && $updater->hasUpdate()) {
        $new_version = $updater->getNewVersion();
        $this->logger->notice("A newer version of Acquia CLI is available. Run <comment>acli self-update</comment> to update to <options=bold>{$new_version}</>");
      }
    } catch (\Exception $e) {
      $this->logger->debug("Could not determine if Acquia CLI has a new version available.");
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
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
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function convertApplicationAliastoUuid(InputInterface $input): void {
    if ($input->hasArgument('applicationUuid') && $input->getArgument('applicationUuid')) {
      $application_uuid_argument = $input->getArgument('applicationUuid');
      try {
        self::validateUuid($application_uuid_argument);
      } catch (ValidatorException $validator_exception) {
        // Since this isn't a valid UUID, let's see if it's a valid alias.
        $alias = $this->normalizeAlias($application_uuid_argument);
        $customer_application = $this->getApplicationFromAlias($alias);
        $input->setArgument('applicationUuid', $customer_application->uuid);
      }
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function convertEnvironmentAliasToUuid(InputInterface $input): void {
    if ($input->hasArgument('environmentId') && $input->getArgument('environmentId')) {
      $env_uuid_argument = $input->getArgument('environmentId');
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
          $input->setArgument('environmentId', $environment->uuid);
        } catch (AcquiaCliException $exception) {
          throw new AcquiaCliException("The {environmentId} must be a valid UUID or site alias.");
        }
      }
    }
  }

  /**
   * @param $cloud_environment
   *
   * @return string
   */
  public static function getSiteGroupFromSshUrl($cloud_environment): string {
    $ssh_url_parts = explode('.', $cloud_environment->sshUrl);
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
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $cloud_environment
   *
   * @return array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getAcsfSites($cloud_environment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloud_environment);
    $command = ['cat', "/var/www/site-php/$sitegroup.{$cloud_environment->name}/multisite-config.json"];
    $process = $this->sshHelper->executeCommand($cloud_environment, $command, FALSE);
    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE);
    }
    throw new AcquiaCliException("Could not get ACSF sites");
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $cloud_environment
   *
   * @return array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getCloudSites($cloud_environment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloud_environment);
    $command = ['ls', "/mnt/files/$sitegroup.{$cloud_environment->name}/sites"];
    $process = $this->sshHelper->executeCommand($cloud_environment, $command, FALSE);
    if ($process->isSuccessful()) {
      return explode("\n", trim($process->getOutput()));
    }
    throw new AcquiaCliException("Could not get Cloud sites");
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $cloud_environment
   *
   * @return mixed
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function promptChooseAcsfSite($cloud_environment) {
    $choices = [];
    $acsf_sites = $this->getAcsfSites($cloud_environment);
    foreach ($acsf_sites['sites'] as $domain => $acsf_site) {
      $choices[] = "{$acsf_site['name']} ($domain)";
    }
    $choice = $this->io->choice('Choose a site', $choices);
    $key = array_search($choice, $choices, TRUE);
    $sites = array_values($acsf_sites['sites']);
    $site = $sites[$key];

    return $site['name'];
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $cloud_environment
   *
   * @return mixed
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function promptChooseCloudSite($cloud_environment) {
    return $this->io->choice('Choose a site', $this->getCloudSites($cloud_environment));
  }

  /**
   * @return mixed
   */
  protected function migrateLegacySendTelemetryPreference() {
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

  public static function getLandoInfo() {
    if ($lando_info = getenv('LANDO_INFO')) {
      return json_decode($lando_info);
    }
    return NULL;
  }

  public static function isLandoEnv() {
    return (bool) self::getLandoInfo();
  }

}
