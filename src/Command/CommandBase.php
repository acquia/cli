<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\AcquiaCliApplication;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Logs;
use AcquiaCloudApi\Response\ApplicationResponse;
use ArrayObject;
use drupol\phposinfo\OsInfo;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

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
   * @var \Symfony\Component\Console\Helper\FormatterHelper*/
  protected $formatter;

  /**
   * @var ApplicationResponse
   */
  private $cloudApplication;

  /**
   * @var array
   */
  protected $localProjectInfo;

  /**
   * @var \Symfony\Component\Console\Helper\QuestionHelper*/
  protected $questionHelper;

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
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $this->formatter = $this->getHelper('formatter');
    $this->setLogger(new ConsoleLogger($output));

    /** @var TelemetryHelper $telemetry_helper */
    $telemetry_helper = $this->getApplication()->getContainer()->get('telemetry_helper');
    /** @var \Zumba\Amplitude\Amplitude $amplitude */
    $amplitude = $this->getApplication()->getContainer()->get('amplitude');
    $telemetry_helper->initializeAmplitude($amplitude, $this->getApplication()->getVersion());
    $this->questionHelper = $this->getHelper('question');
    $telemetry_helper->checkTelemetryPreference($this->questionHelper);

    /** @var \Acquia\Cli\AcquiaCliApplication $application */
    $application = $this->getApplication();

    /** @var \Webmozart\KeyValueStore\JsonFileStore $cloud_datastore */
    $cloud_datastore = $this->getApplication()->getContainer()->get('cloud_datastore');
    if ($this->commandRequiresAuthentication() && !$application::isMachineAuthenticated($cloud_datastore)) {
      throw new AcquiaCliException('This machine is not yet authenticated with Acquia Cloud. Please run `acli auth:login`');
    }

    $this->loadLocalProjectInfo();
  }

  public function run(InputInterface $input, OutputInterface $output) {
    $exit_code = parent::run($input, $output);
    $event_properties = [
      'exit_code' => $exit_code,
      'arguments' => $input->getArguments(),
      'options' => $input->getOptions(),
    ];
    /** @var \Zumba\Amplitude\Amplitude $amplitude */
    $amplitude = $this->getApplication()->getContainer()->get('amplitude');
    $amplitude->queueEvent('Ran command', $event_properties);

    return $exit_code;
  }

  /**
   * @return bool
   */
  protected function commandRequiresAuthentication(): bool {
    // In fact some other commands such as `api:list` don't require auth, but it's easier and safer to assume they do.
    return $this->input->getFirstArgument() !== 'auth:login';
  }

  /**
   * Gets the application instance for this command.
   *
   * @return \Acquia\Cli\AcquiaCliApplication|\Symfony\Component\Console\Application
   */
  public function getApplication() {
    return parent::getApplication();
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   *
   * @return mixed
   */
  protected function promptChooseApplication(
    Client $acquia_cloud_client
  ) {
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();
    $application = $this->promptChooseFromObjects(
      $customer_applications,
      'uuid',
      'name',
      'Please select an Acquia Cloud application:'
    );

    return $application;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   *
   * @param string $application_uuid
   *
   * @return mixed
   */
  protected function promptChooseEnvironment(
    Client $acquia_cloud_client,
    string $application_uuid
  ) {
    $environment_resource = new Environments($acquia_cloud_client);
    $environments = $environment_resource->getAll($application_uuid);
    $environment = $this->promptChooseFromObjects(
      $environments,
      'uuid',
      'name',
      'Please select an Acquia Cloud environment:'
    );
    return $environment;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   *
   * @param string $environment_id
   *
   * @return mixed
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

  protected function loadLocalProjectInfo() {
    $this->logger->debug('Loading local project information...');
    $local_user_config = $this->getApplication()->getContainer()->get('acli_datastore')->get($this->getApplication()->getContainer()->getParameter('acli_config.filename'));
    // Save empty local project info.
    // @todo Abstract this.
    if ($local_user_config !== NULL && $this->getApplication()->getContainer()->getParameter('repo_root') !== NULL) {
      $this->logger->debug('Searching local datastore for matching project...');
      foreach ($local_user_config['localProjects'] as $project) {
        if ($project['directory'] === $this->getApplication()->getContainer()->getParameter('repo_root')) {
          $this->logger->debug('Matching local project found.');
          $this->localProjectInfo = $project;
          return;
        }
      }
    }
    else {
      $this->logger->debug("No matching local project found.");
      $local_user_config = [];
    }

    if ($this->getApplication()->getContainer()->getParameter('repo_root')) {
      $this->creatLocalProjectStubInConfig($local_user_config);
    }
  }

  /**
   * @param \Acquia\Cli\AcquiaCliApplication $application
   *
   * @return array|null
   */
  protected function getGitConfig(AcquiaCliApplication $application): ?array {
    $file_path = $application->getContainer()->getParameter('repo_root') . '/.git/config';
    if (file_exists($file_path)) {
      return parse_ini_file($file_path, TRUE);
    }

    return NULL;
  }

  /**
   * @param $git_config
   *
   * @return array
   */
  protected function getGitRemotes($git_config): array {
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
    $progressBar->setMessage("Searching <comment>$count applications</comment> on Acquia Cloud...");
    $progressBar->start();

    // Search Cloud applications.
    foreach ($customer_applications as $application) {
      $progressBar->setMessage("Searching <comment>{$application->name}</comment> for matching git URLs");
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
   * @param \Acquia\Cli\AcquiaCliApplication $application
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   *
   * @return \AcquiaCloudApi\Response\ApplicationResponse|null
   */
  protected function inferCloudAppFromLocalGitConfig(
    AcquiaCliApplication $application,
    Client $acquia_cloud_client
    ): ?ApplicationResponse {
    if ($application->getContainer()->getParameter('repo_root')) {
      $this->output->writeln("There is no Acquia Cloud application linked to <comment>{$application->getContainer()->getParameter('repo_root')}/.git</comment>.");
      $question = new ConfirmationQuestion('<question>Would you like Acquia CLI to search for a Cloud application that matches your local git config?</question> ');
      $helper = $this->getHelper('question');
      $answer = $helper->ask($this->input, $this->output, $question);
      if ($answer) {
        $this->output->writeln('Searching for a matching Cloud application...');
        if ($git_config = $this->getGitConfig($application)) {
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
   * @param $application_uuid
   *
   * @return mixed
   * @throws \Exception
   */
  protected function determineCloudEnvironment($application_uuid) {
    $acquia_cloud_client = $this->getApplication()->getContainer()->get('cloud_api')->getClient();
    $environment = $this->promptChooseEnvironment($acquia_cloud_client, $application_uuid);
    return $environment->uuid;

  }

  /**
   * @param bool $link_app
   *
   * @return string|null
   * @throws \Exception
   */
  protected function determineCloudApplication($link_app = FALSE): ?string {
    $application_uuid = $this->doDetermineCloudApplication();
    if (isset($application_uuid)) {
      $acquia_cloud_client = $this->getApplication()->getContainer()->get('cloud_api')->getClient();
      $applications_resource = new Applications($acquia_cloud_client);
      $application = $applications_resource->get($application_uuid);
      if (!$this->getAppUuidFromLocalProjectInfo()) {
        if ($link_app) {
          $this->saveLocalConfigCloudAppUuid($application);
        }
        else {
          $this->promptLinkApplication($application);
        }
      }
    }

    return $application_uuid;
  }

  protected function doDetermineCloudApplication() {
    $acquia_cloud_client = $this->getApplication()->getContainer()->get('cloud_api')->getClient();
    /** @var \Acquia\Cli\AcquiaCliApplication $cli_application */
    $cli_application = $this->getApplication();

    if ($this->input->hasOption('cloud-app-uuid') && $this->input->getOption('cloud-app-uuid')) {
      $cloud_application_uuid = $this->input->getOption('cloud-app-uuid');
      return $this->validateUuid($cloud_application_uuid);
    }

    // Try local project info.
    if ($application_uuid = $this->getAppUuidFromLocalProjectInfo()) {
      return $application_uuid;
    }

    // If an IDE, get from env var.
    if (self::isAcquiaCloudIde() && $application_uuid = self::getThisCloudIdeCloudAppUuid()) {
      return $application_uuid;
    }

    // Try to guess based on local git url config.
    if ($cloud_application = $this->inferCloudAppFromLocalGitConfig($cli_application, $acquia_cloud_client)) {

      return $cloud_application->uuid;
    }

    // Finally, just ask.
    if ($application = $this->promptChooseApplication($acquia_cloud_client)) {
      return $application->uuid;
    }

    return NULL;
  }

  /**
   * @param $uuid
   *
   * @return mixed
   */
  protected function validateUuid($uuid) {
    $violations = Validation::createValidator()->validate($uuid, [
      new NotBlank(),
      new Uuid(),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $uuid;
  }

  /**
   * @param \AcquiaCloudApi\Response\ApplicationResponse $application
   */
  protected function saveLocalConfigCloudAppUuid(ApplicationResponse $application): void {
    $local_user_config = $this->getApplication()->getContainer()->get('acli_datastore')->get($this->getApplication()->getContainer()->getParameter('acli_config.filename'));
    if (!$local_user_config) {
      $local_user_config = [
        'localProjects' => [],
      ];
    }
    foreach ($local_user_config['localProjects'] as $key => $project) {
      if ($project['directory'] === $this->getApplication()->getContainer()->getParameter('repo_root')) {
        $project['cloud_application_uuid'] = $application->uuid;
        $local_user_config['localProjects'][$key] = $project;
        $this->localProjectInfo = $local_user_config;
        $this->getApplication()->getContainer()->get('acli_datastore')->set($this->getApplication()->getContainer()->getParameter('acli_config.filename'), $local_user_config);
        $this->output->writeln("<info>The Cloud application <comment>{$application->name}</comment> has been linked to this repository</info>");
        return;
      }
    }
  }

  /**
   * @return mixed
   */
  protected function getAppUuidFromLocalProjectInfo() {
    if (isset($this->localProjectInfo) && array_key_exists('cloud_application_uuid', $this->localProjectInfo)) {
      return $this->localProjectInfo['cloud_application_uuid'];
    }

    return NULL;
  }

  /**
   * @param \AcquiaCloudApi\Response\ApplicationResponse|null $cloud_application
   */
  protected function promptLinkApplication(
    ?ApplicationResponse $cloud_application
    ): void {
    $question = new ConfirmationQuestion("<question>Would you like to link the Cloud application {$cloud_application->name} to this repository</question>? ");
    $helper = $this->getHelper('question');
    $answer = $helper->ask($this->input, $this->output, $question);
    if ($answer) {
      $this->saveLocalConfigCloudAppUuid($cloud_application);
    }
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validateCwdIsValidDrupalProject(): void {
    if (!$this->getApplication()->getContainer()->getParameter('repo_root')) {
      throw new AcquiaCliException('Could not find a local Drupal project. Looked for `docroot/index.php`. Please execute this command from within a Drupal project directory.');
    }
  }

  /**
   * @return bool
   */
  public static function isAcquiaCloudIde(): bool {
    return AcquiaDrupalEnvironmentDetector::getAhEnv() === 'IDE';
  }

  /**
   * @return array|false|string
   */
  protected static function getThisCloudIdeCloudAppUuid() {
    return getenv('ACQUIA_APPLICATION_UUID');
  }

  /**
   * @return false|string
   */
  public static function getThisCloudIdeUuid() {
    return getenv('REMOTEIDE_UUID');
  }

  /**
   * @param array $local_user_config
   */
  protected function creatLocalProjectStubInConfig(
    array $local_user_config
  ): void {
    $project = [];
    $project['name'] = basename($this->getApplication()->getContainer()->getParameter('repo_root'));
    $project['directory'] = $this->getApplication()->getContainer()->getParameter('repo_root');
    $local_user_config['localProjects'][] = $project;

    $this->localProjectInfo = $local_user_config;
    $this->logger->debug('Saving local project information.');
    $this->getApplication()->getContainer()->get('acli_datastore')->set($this->getApplication()->getContainer()->getParameter('acli_config.filename'), $local_user_config);
  }

}
