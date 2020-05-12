<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\AcquiaCliApplication;
use Acquia\Cli\Connector\CliCloudConnector;
use Acquia\Cli\DataStore\DataStoreInterface;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Spinner\Spinner;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\ApplicationResponse;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
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
   * @var \Acquia\Cli\DataStore\DataStoreInterface
   */
  private $datastore;

  private $cloudApplication;

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
    $this->datastore = $application->getDatastore();

    if ($this->commandRequiresAuthentication() && !$this->isMachineAuthenticated()) {
      throw new AcquiaCliException('This machine is not yet authenticated with Acquia Cloud. Please run `acli auth:login`');
    }

    $this->loadLocalProjectInfo();
  }

  /**
   * @return bool
   */
  protected function isMachineAuthenticated(): bool {
    $cloud_api_conf = $this->datastore->get($this->getApplication()->getCloudConfigFilename());
    return $cloud_api_conf !== NULL && array_key_exists('key', $cloud_api_conf) && array_key_exists('secret', $cloud_api_conf);
  }

  /**
   * @return bool
   */
  protected function commandRequiresAuthentication(): bool {
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
   * @return \Acquia\Cli\DataStore\DataStoreInterface
   */
  public function getDatastore(): DataStoreInterface {
    return $this->datastore;
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
  protected function getAcquiaCloudClient(): Client {
    $cloud_api_conf = $this->datastore->get('cloud_api.conf');
    $config = [
      'key' => $cloud_api_conf['key'],
      'secret' => $cloud_api_conf['secret'],
    ];
    $connector = new CliCloudConnector($config);
    return Client::factory($connector);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   *
   * @return mixed
   */
  protected function promptChooseApplication(
    InputInterface $input,
    OutputInterface $output,
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
   * @param \stdClass[] $items An array of objects.
   * @param string $unique_property The property of the $item that will be used to identify the object.
   * @param string $label_property
   * @param string $question_text
   *
   * @return null|object
   */
  public function promptChooseFromObjects($items, $unique_property, $label_property, $question_text) {
    $list = [];
    foreach ($items as $item) {
      $list[$item->$unique_property] = $item->$label_property;
    }
    $labels = array_values($list);
    $question = new ChoiceQuestion($question_text, $labels);
    $helper = $this->getHelper('question');
    $choice_id = $helper->ask($this->input, $this->output, $question);
    $identifier = array_search($choice_id, $list, TRUE);
    foreach ($items as $item) {
      if ($item->$unique_property === $identifier) {
        return $item;
      }
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
      if ((strpos($section_name, 'remote ') !== FALSE) && strpos($section['url'], 'acquia.com')) {
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
      $progressBar->setMessage("Searching <comment>{$application->name}</comment> for git URLs that match local git config.");
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
          $cloud_application = $this->findCloudApplicationByGitUrl($acquia_cloud_client,
            $local_git_remotes);

          return $cloud_application;
        }
      }
    }

    return NULL;
  }

  /**
   * @return string|null
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determineCloudApplication(): ?string {
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
    if ($this::isAcquiaRemoteIde() && $application_uuid = $this::getThisRemoteIdeUuid()) {
      return $application_uuid;
    }

    // Try to guess based on local git url config.
    if ($cloud_application = $this->inferCloudAppFromLocalGitConfig($cli_application, $acquia_cloud_client)) {
      $this->output->writeln('<info>Found a matching application!</info>');
      $this->promptLinkApplication($cli_application, $cloud_application);

      return $cloud_application->uuid;
    }

    // Finally, just ask.
    if ($application = $this->promptChooseApplication($this->input, $this->output, $acquia_cloud_client)) {
      $this->saveLocalConfigCloudAppUuid($application->uuid);
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
  protected function saveLocalConfigCloudAppUuid($application_uuid): void {
    $local_user_config = $this->getDatastore()->get($this->getApplication()->getAcliConfigFilename());
    if (!$local_user_config) {
      $local_user_config = [
        'localProjects' => [],
      ];
    }
    foreach ($local_user_config['localProjects'] as $key => $project) {
      if ($project['directory'] === $this->getApplication()->getRepoRoot()) {
        $project['cloud_application_uuid'] = $application_uuid;
        $local_user_config['localProjects'][$key] = $project;
        $this->localProjectInfo = $local_user_config;
        $this->getDatastore()->set($this->getApplication()->getAcliConfigFilename(), $local_user_config);
        $this->output->writeln("<info>The Cloud application with uuid <comment>$application_uuid</comment> has been linked to the repository <comment>{$project['directory']}</comment></info>");
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
    $question = new ConfirmationQuestion("<question>Would you like to link the project at {$cli_application->getRepoRoot()} with the Cloud App \"{$cloud_application->name}\"</question>? ");
    $helper = $this->getHelper('question');
    $answer = $helper->ask($this->input, $this->output, $question);
    if ($answer) {
      $this->saveLocalConfigCloudAppUuid($cloud_application->uuid);
      $this->output->writeln("Your local repository is now linked with {$cloud_application->name}.");
    }
  }

  /**
   *
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
   * @return false|string
   */
  public static function getThisRemoteIdeUuid() {
    return getenv('REMOTEIDE_UUID');
  }

  /**
   * @param \React\EventLoop\LoopInterface $loop
   *
   * @param string $message
   *
   * @return \Acquia\Cli\Output\Spinner\Spinner
   */
  public function addSpinnerToLoop(
    LoopInterface $loop,
    $message
  ): Spinner {
      $spinner = new Spinner($this->output, 4);
      $spinner->setMessage($message);
      $spinner->start();
      $loop->addPeriodicTimer($spinner->interval(),
        static function () use ($spinner) {
          $spinner->advance();
        });

    return $spinner;
  }

  protected function finishSpinner(Spinner $spinner) {
    $spinner->finish();
  }

  /**
   * @param \React\EventLoop\LoopInterface $loop
   * @param $minutes
   * @param \Acquia\Cli\Output\Spinner\Spinner $spinner
   */
  public function addTimeoutToLoop(
    LoopInterface $loop,
    $minutes,
    Spinner $spinner
  ): void {
    $loop->addTimer($minutes * 60, function () use ($loop, $minutes, $spinner) {
      $this->finishSpinner($spinner);
      $this->logger->debug("Timed out after $minutes minutes!");
      $loop->stop();
    });
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
