<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\AcquiaCliApplication;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\CloudApiDataStoreAwareTrait;
use Acquia\Cli\Helpers\DataStoreAwareTrait;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Logs;
use AcquiaCloudApi\Response\ApplicationResponse;
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

  use CloudApiDataStoreAwareTrait;
  use DataStoreAwareTrait;
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
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $this->formatter = $this->getHelper('formatter');
    $this->setLogger(new ConsoleLogger($output));
    $this->questionHelper = $this->getHelper('question');

    /** @var \Acquia\Cli\AcquiaCliApplication $application */
    $application = $this->getApplication();
    $this->setDatastore($application->getDatastore());
    $this->setCloudApiDatastore($application->getCloudApiDatastore());

    if ($this->commandRequiresAuthentication() && !$application->isMachineAuthenticated()) {
      throw new AcquiaCliException('This machine is not yet authenticated with Acquia Cloud. Please run `acli auth:login`');
    }

    $this->loadLocalProjectInfo();
    $this->checkTelemetryPreference();
  }

  /**
   * Check if telemetry preference is set, prompt if not.
   */
  protected function checkTelemetryPreference() {
    $datastore = $this->getDatastore();
    $telemetry = $datastore->get(DataStoreContract::SEND_TELEMETRY);
    if (!isset($telemetry) && $this->input->isInteractive()) {
      $this->output->writeln('We strive to give you the best tools for development.');
      $this->output->writeln('You can really help us improve by sharing anonymous performance and usage data.');
      $question = new ConfirmationQuestion('<question>Would you like to share anonymous performance usage and data?</question>', TRUE);
      $helper = $this->getHelper('question');
      $pref = $helper->ask($this->input, $this->output, $question);
      $datastore->set(DataStoreContract::SEND_TELEMETRY, $pref);
      if ($pref) {
        $this->output->writeln('Awesome! Thank you for helping!');
      }
      else {
        $this->output->writeln('Ok, no data will be collected and shared with us.');
        $this->output->writeln('We take privacy seriously.');
        $this->output->writeln('If you change your mind, run <comment>acli telemetry</comment>.');
      }
    }
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
   * @param \stdClass[] $items An array of objects.
   * @param string $unique_property The property of the $item that will be used to identify the object.
   * @param string $label_property
   * @param string $question_text
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
    $local_user_config = $this->getDatastore()->get($this->getApplication()->getAcliConfigFilename());
    // Save empty local project info.
    // @todo Abstract this.
    if ($local_user_config !== NULL && $this->getApplication()->getRepoRoot() !== NULL) {
      $this->logger->debug('Searching local datastore for matching project...');
      foreach ($local_user_config['localProjects'] as $project) {
        if ($project['directory'] === $this->getApplication()->getRepoRoot()) {
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

    if ($this->getApplication()->getRepoRoot()) {
      $this->creatLocalProjectStubInConfig($local_user_config);
    }
  }

  /**
   * @param \Acquia\Cli\AcquiaCliApplication $application
   *
   * @return array|null
   */
  protected function getGitConfig(AcquiaCliApplication $application): ?array {
    $file_path = $application->getRepoRoot() . '/.git/config';
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
    if ($application->getRepoRoot()) {
      $this->output->writeln("There is no Acquia Cloud application linked to <comment>{$application->getRepoRoot()}/.git</comment>.");
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
   */
  protected function determineCloudEnvironment($application_uuid) {
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $environment = $this->promptChooseEnvironment($acquia_cloud_client, $application_uuid);
    return $environment->uuid;

  }

  /**
   * @param bool $link_app
   *
   * @return string|null
   */
  protected function determineCloudApplication($link_app = FALSE): ?string {
    $application_uuid = $this->doDetermineCloudApplication();
    if (isset($application_uuid)) {
      $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
      $applications_resource = new Applications($acquia_cloud_client);
      $application = $applications_resource->get($application_uuid);
      if (!$this->getAppUuidFromLocalProjectInfo()) {
        if ($link_app) {
          $this->saveLocalConfigCloudAppUuid($application);
        }
        else {
          $this->promptLinkApplication($this->getApplication(), $application);
        }
      }
    }

    return $application_uuid;
  }

  protected function doDetermineCloudApplication() {
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
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
    if (self::isAcquiaRemoteIde() && $application_uuid = self::getThisRemoteIdeCloudAppUuid()) {
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
   * @param string $application_uuid
   */
  protected function saveLocalConfigCloudAppUuid(ApplicationResponse $application): void {
    $local_user_config = $this->getDatastore()->get($this->getApplication()->getAcliConfigFilename());
    if (!$local_user_config) {
      $local_user_config = [
        'localProjects' => [],
      ];
    }
    foreach ($local_user_config['localProjects'] as $key => $project) {
      if ($project['directory'] === $this->getApplication()->getRepoRoot()) {
        $project['cloud_application_uuid'] = $application->uuid;
        $local_user_config['localProjects'][$key] = $project;
        $this->localProjectInfo = $local_user_config;
        $this->getDatastore()->set($this->getApplication()->getAcliConfigFilename(), $local_user_config);
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
   * @param \Acquia\Cli\AcquiaCliApplication $cli_application
   * @param \AcquiaCloudApi\Response\ApplicationResponse|null $cloud_application
   */
  protected function promptLinkApplication(
    AcquiaCliApplication $cli_application,
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
    if (!$this->getApplication()->getRepoRoot()) {
      throw new AcquiaCliException('Could not find a local Drupal project. Looked for `docroot/index.php`. Please execute this command from within a Drupal project directory.');
    }
  }

  /**
   * @return bool
   */
  public static function isAcquiaRemoteIde(): bool {
    return AcquiaDrupalEnvironmentDetector::getAhEnv() === 'IDE';
  }

  /**
   * @return array|false|string
   */
  protected static function getThisRemoteIdeCloudAppUuid() {
    return getenv('ACQUIA_APPLICATION_UUID');
  }

  /**
   * @return false|string
   */
  public static function getThisRemoteIdeUuid() {
    return getenv('REMOTEIDE_UUID');
  }

  /**
   * @param array $local_user_config
   */
  protected function creatLocalProjectStubInConfig(
    array $local_user_config
  ): void {
    $project = [];
    $project['name'] = basename($this->getApplication()->getRepoRoot());
    $project['directory'] = $this->getApplication()->getRepoRoot();
    $local_user_config['localProjects'][] = $project;

    $this->localProjectInfo = $local_user_config;
    $this->logger->debug('Saving local project information.');
    $this->getDatastore()->set($this->getApplication()->getAcliConfigFilename(), $local_user_config);
  }

}
