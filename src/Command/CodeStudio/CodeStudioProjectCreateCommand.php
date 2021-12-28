<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\WizardCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Response\ApplicationResponse;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\HttpClient\Builder;
use stdClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CodeStudioProjectCreateCommand.
 */
class CodeStudioProjectCreateCommand extends WizardCommandBase {

  protected static $defaultName = 'codestudio:project:create';

  /**
   * @var string
   */
  private $appUuid;

  /**
   * @var \Gitlab\Client
   */
  private $gitLabClient;

  /**
   * @var string
   */
  private $gitLabProjectDescription;

  /**
   * @var array
   */
  private $gitLabAccount;

  /**
   * @var \Acquia\Cli\Output\Checklist
   */
  private $checklist;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Create and configure a new Code Studio project for a given Acquia Cloud application')
      ->addOption('cloud-key', NULL, InputOption::VALUE_REQUIRED, 'The Cloud API Token key that Code Studio will use')
      ->addOption('cloud-secret', NULL, InputOption::VALUE_REQUIRED, 'The Cloud API Token secret that Code Studio will use')
      ->addOption('gitlab-token', NULL, InputOption::VALUE_REQUIRED, 'The GitLab personal access token that will be used to communicate with GitLab instance')
      ->addOption('gitlab-host', NULL, InputOption::VALUE_REQUIRED, 'The URL of the GitLab instance')
      ->addOption('gitlab-project-id', NULL, InputOption::VALUE_REQUIRED, 'The project ID (an integer) of the GitLab project to configure.')
      ->setAliases(['codestudio:wizard', 'cs:wizard']);
    $this->acceptApplicationUuid();
    $this->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return void
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    //$this->requireCloudIdeEnvironment();
    parent::initialize($input, $output);
    $this->checklist = new Checklist($output);
    $this->appUuid = $this->determineCloudApplication();
    $this->setSshKeyFilepath($this->getSshKeyFilename($this->appUuid));
    $this->passphraseFilepath = $this->localMachineHelper->getLocalFilepath('~/.codestudio_passphrase');
  }

  /**
   * @param string $app_uuid
   *
   * @return string
   */
  public static function getSshKeyFilename(string $app_uuid): string {
    return 'id_rsa_codestudio_' . $app_uuid;
  }

  /**
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function deleteThisSshKeyFromCloud(): void {
    if ($cloud_key = $this->findGitLabSshKeyOnCloud()) {
      $this->deleteSshKeyFromCloud($cloud_key);
    }
  }

  /**
   * @return \stdClass|null
   */
  protected function findGitLabSshKeyOnCloud(): ?stdClass {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $ssh_key_label = $this->getSshKeyLabel();
    foreach ($cloud_keys as $cloud_key) {
      if ($cloud_key->label === $ssh_key_label) {
        return $cloud_key;
      }
    }
    return NULL;
  }

  /**
   *
   * @param string $app_uuid
   *
   * @return string
   */
  public static function getGitLabSshKeyLabel(string $app_uuid): string {
    return self::normalizeSshKeyLabel('CODESTUDIO_' . $app_uuid);
  }

  /**
   * @return string
   */
  protected function getSshKeyLabel(): string {
    return $this::getGitLabSshKeyLabel($this->appUuid);
  }

  /**
   */
  protected function validateEnvironment(): bool {
    return TRUE;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Get Cloud access tokens.
    $cloud_key = $this->determineCloudKey();
    $cloud_secret = $this->determineCloudSecret();
    // We may already be authenticated with Acquia Cloud via a refresh token.
    // But, we specifically need an API Token key-pair of Code Studio.
    // So we reauthenticate to be sure we're using the provided credentials.
    $this->reAuthenticate($cloud_key, $cloud_secret, $this->cloudCredentials->getBaseUri());

    // Get Cloud application.
    $cloud_application_uuid = $this->determineCloudApplication();
    $cloud_application = $this->getCloudApplication($cloud_application_uuid);

    // Get Cloud account.
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $account_adapter = new Account($acquia_cloud_client);
    $account = $account_adapter->get();
    $this->validateRequiredCloudPermissions($acquia_cloud_client, $cloud_application_uuid, $account);

    // Authenticate with GitLab.
    $gitlab_host = $this->getGitLabHost();
    $gitlab_token = $this->getGitLabToken($gitlab_host);
    // @todo Don't bypass SSL. Not sure why cert isn't valid.
    $this->gitLabClient = new Client(new Builder(new \GuzzleHttp\Client(['verify' => FALSE])));
    $this->gitLabClient->setUrl('https://' . $gitlab_host);
    $this->gitLabClient->authenticate($gitlab_token, Client::AUTH_OAUTH_TOKEN);
    try {
      $this->gitLabAccount = $this->gitLabClient->users()->me();
    }
    catch (RuntimeException $exception) {
      $this->io->error([
        "Unable to authenticate with Code Studio",
        "Did you set a valid token with the api and write_repository scopes?",
        "Try running `glab auth login` to re-authenticate.",
        "Then try again.",
      ]);
      return 1;
    }

    $this->gitLabProjectDescription = "Source repository for Acquia Cloud Platform application <comment>$cloud_application_uuid</comment>";
    $project = $this->determineGitLabProject($cloud_application);

    $this->io->writeln([
      "",
      "This command will configure the Code Studio project <comment>{$project['path_with_namespace']}</comment> for automatic deployment to the",
      "Acquia Cloud Platform application <comment>{$cloud_application->name}</comment> (<comment>$cloud_application_uuid</comment>)",
      "using credentials (API Token and SSH Key) belonging to <comment>{$account->mail}</comment>.",
      "",
      "If the <comment>{$account->mail}</comment> Cloud account is deleted in the future, this Code Studio project will need to be re-configured.",
    ]);
    $answer = $this->io->confirm('Do you want to continue?');
    if (!$answer) {
      return 0;
    }

    $this->io->writeln([
      "Creating an SSH key belonging to <comment>{$account->mail}</comment> for Code Studio to use..."
    ]);
    parent::execute($input, $output);

    $project_access_token_name = 'acquia-codestudio';
    $project_access_token = $this->createProjectAccessToken($project, $project_access_token_name);

    // Setting the description to match the known pattern will allow us to automatically find the project next time.
    if ($project['description'] != $this->gitLabProjectDescription) {
      $this->gitLabClient->projects()->update($project['id'], ['description' => $this->gitLabProjectDescription]);
    }

    $this->setGitLabCiCdVariables($project, $cloud_application_uuid, $cloud_key, $cloud_secret, $project_access_token_name, $project_access_token);
    $this->createScheduledPipeline($project);

    $this->io->success([
      "Successfully configured the Code Studio project!",
      "This project will now use Acquia's Drupal optimized AutoDevops to build, test, and deploy your code automatically to Acquia Cloud Platform via CI/CD pipelines.",
      "You can visit it here:",
      $project['web_url']
    ]);
    $this->io->note(["If the {$account->mail} Cloud account is deleted in the future, this Code Studio project will need to be re-configured."]);

    return 0;
  }

  protected function getGitLabScheduleByDescription($project, $scheduled_pipeline_description) {
    $existing_schedules = $this->gitLabClient->schedules()->showAll($project['id']);
    foreach ($existing_schedules as $schedule) {
      if ($schedule['description'] == $scheduled_pipeline_description) {
        return TRUE;
      }
    }
  }

  protected function getGitLabProjectAccessTokenByName($project, $name) {
    $existing_project_access_tokens = $this->gitLabClient->projects()->projectAccessTokens($project['id']);
    foreach ($existing_project_access_tokens as $key => $token) {
      if ($token['name'] == $name) {
        return $token;
      }
    }
    return NULL;
  }

  /**
   * @param ApplicationResponse $cloud_application
   *
   * @return array
   */
  protected function determineGitLabProject(ApplicationResponse $cloud_application): array {
    // Use command option.
    if ($this->input->getOption('gitlab-project-id')) {
      $id = $this->input->getOption('gitlab-project-id');
      return $this->gitLabClient->projects()->show($id);
    }
    // Search for existing project that matches expected description pattern.
    $projects = $this->gitLabClient->projects()->all(['search' => $cloud_application->uuid]);
    if ($projects) {
      if (count($projects) == 1) {
        return reset($projects);
      }
      else {
        return $this->promptChooseFromObjectsOrArrays(
          $this->gitLabClient->projects()->all(),
          'id',
          'path_with_namespace',
          "Found multiple projects that could match the {$cloud_application->name} application. Please choose which one to configure.",
          FALSE
        );
      }
    }
    // Prompt to create project.
    else {
      $this->io->writeln([
        "",
        "Could not find any existing Code Studio project for <comment>{$cloud_application->name}</comment>.",
        "Searched for UUID <comment>{$cloud_application->uuid}</comment> in project descriptions.",
      ]);
      $create_project = $this->io->confirm('Would you like to created a new Code Studio project? If you select "no" you may choose from a full list of available projects.');
      if ($create_project) {
        $project = $this->gitLabClient->projects()->create($cloud_application->name, [
          'description' => $this->gitLabProjectDescription,
          'topics' => 'Acquia Cloud Application'
        ]);
        $this->io->success("Created {$project['path_with_namespace']} Code Studio project.");
        return $project;
      }
      // Prompt to choose from full list, regardless of description.
      else {
        return $this->promptChooseFromObjectsOrArrays(
          $this->gitLabClient->projects()->all(),
          'id',
          'path_with_namespace',
          "Please choose a Code Studio project to configure for the {$cloud_application->name} application",
          FALSE
        );
      }
    }
  }

  /**
   * @return mixed
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determineCloudKey() {
    if ($this->input->getOption('cloud-key')) {
      return $this->input->getOption('cloud-key');
    }
    if ($this->input->isInteractive()) {
      $this->io->note([
        'Please enter a Cloud API Token key',
        'This will be used by Code Studio to do things like create CDEs.',
      ]);
      return $this->io->ask('Please enter a Cloud API Token key');
    }
    throw new AcquiaCliException('You must provide a Cloud API Token key');
  }

  /**
   * @return mixed
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determineCloudSecret() {
    if ($this->input->getOption('cloud-secret')) {
      return $this->input->getOption('cloud-secret');
    }
    if ($this->input->isInteractive()) {
      $this->io->note([
        'Please enter a Cloud API Token secret',
        'This will be used by Code Studio to do things like create CDEs.',
      ]);
      return $this->io->ask('Please enter a Cloud API Token secret');
    }
    throw new AcquiaCliException('You must provide a Cloud API Token secret');
  }

  /**
   * @param array $gitlab_config
   *
   * @return string
   */
  protected function getGitLabToken($gitlab_host): string {
    if ($this->input->getOption('gitlab-token')) {
      return $this->input->getOption('gitlab-token');
    }
    $process = $this->localMachineHelper->execute([
      'glab',
      'config',
      'get',
      'token',
      '--host=' . $gitlab_host,
    ], NULL, NULL, FALSE);
    if ($process->isSuccessful() && trim($process->getOutput())) {
      return trim($process->getOutput());
    }

    $this->io->writeln([
      "",
      "You must first authenticate with Code Studio by creating a personal access token:",
      "* Visit https://$gitlab_host/-/profile/personal_access_tokens",
      "* Create a token and grant it both <comment>api</comment> and <comment>write repository</comment> scopes",
      "* Copy the token to your clipboard",
      "* Run <comment>glab auth login --hostname=$gitlab_host</comment> and paste the token when prompted",
      "* Try this command again.",
    ]);

    throw new AcquiaCliException("Could not determine GitLab token");
  }

  /**
   * @return array
   */
  protected function getGitLabConfig(): array {
    $glab_config_filepath = $this->localMachineHelper->getLocalFilepath('~/.config/glab-cli/config.yml');
    if ($this->localMachineHelper->getFilesystem()->exists($glab_config_filepath)) {
      return Yaml::parse($this->localMachineHelper->readFile($glab_config_filepath));
    }
    return [];
  }

  /**
   * @return string
   */
  protected function getGitLabHost(): string {
    $process = $this->localMachineHelper->execute([
      'glab',
      'config',
      'get',
      'host',
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Could not determine GitLab host: @error_message", ['@error_message' => $process->getErrorOutput()]);
    }
    return trim($process->getOutput());
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param string|null $cloud_application_uuid
   * @param \AcquiaCloudApi\Response\AccountResponse $account
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validateRequiredCloudPermissions(\AcquiaCloudApi\Connector\Client $acquia_cloud_client, ?string $cloud_application_uuid, \AcquiaCloudApi\Response\AccountResponse $account): void {
    $required_permissions = [
      "deploy to non-prod",
      # Add SSH key to git repository
      "add ssh key to git",
      # Add SSH key to non-production environments
      "add ssh key to non-prod",
      # Add a CD environment
      "add an environment",
      # Delete a CD environment
      "delete an environment",
      # Manage environment variables on a non-production environment
      "administer environment variables on non-prod",
    ];

    $permissions = $acquia_cloud_client->request('get', "/applications/{$cloud_application_uuid}/permissions");
    $keyed_permissions = [];
    foreach ($permissions as $permission) {
      $keyed_permissions[$permission->name] = $permission;
    }
    foreach ($required_permissions as $name) {
      if (!array_key_exists($name, $keyed_permissions)) {
        throw new AcquiaCliException("The Acquia Cloud account @account does not have the required @name permission.", [
          '@account' => $account->mail,
          '@name' => $name
        ]);
      }
    }
  }

  /**
   * @param array $project
   * @param string $project_access_token_name
   *
   * @return mixed
   */
  protected function createProjectAccessToken(array $project, string $project_access_token_name) {
    $this->io->writeln("Creating project access token...");
    try {
      if ($existing_token = $this->getGitLabProjectAccessTokenByName($project, $project_access_token_name)) {
        $this->checklist->addItem("Deleted access token named <comment>$project_access_token_name</comment>");
        $this->gitLabClient->projects()
            ->deleteProjectAccessToken($project['id'], $existing_token['id']);
        $this->checklist->completePreviousItem();
      }
      $this->checklist->addItem("Creating access token named <comment>$project_access_token_name</comment>");
      $project_access_token = $this->gitLabClient->projects()
          ->createProjectAccessToken($project['id'], [
          'name' => $project_access_token_name,
          'scopes' => ['api', 'write_repository'],
        ]);
      $this->checklist->completePreviousItem();
      return $project_access_token['token'];
    } catch (\Exception $exception) {
      $this->io->warning("Since you're a GitLab admin, we tried to create the project access token automatically. That didn't work, probably because the access token you're using doesn't have the sudo scope. Let's try to do this manually.");
    }

    $existing_token = $this->getGitLabProjectAccessTokenByName($project, $project_access_token_name);
    $this->io->writeln([
      "Next, you need to create a project access token for <comment>{$project['path_with_namespace']}</comment>:",
      "* Visit {$project['web_url']}/-/settings/access_tokens",
    ]);
    if ($existing_token) {
      $this->io->writeln(["* Revoke the existing access token named <comment>$project_access_token_name</comment>"]);
    }
    $this->io->writeln([
      "* Name it <comment>$project_access_token_name</comment> and grant it both <comment>api</comment> and <comment>write repository</comment> scopes",
      "* Copy the token to your clipboard so that you can paste it below"
    ]);
    $project_access_token = $this->io->ask('Enter the project access token');
    if (!$this->getGitLabProjectAccessTokenByName($project, $project_access_token_name)) {
      $this->io->warning(["Oops! That didn't work. Did you name the access token correctly? Let's try again..."]);
      $this->createProjectAccessToken($project, $project_access_token_name);
    }

    return $project_access_token;
  }

  /**
   * @param array $project
   * @param string|null $cloud_application_uuid
   * @param $cloud_key
   * @param $cloud_secret
   * @param string $project_access_token_name
   * @param $project_access_token
   */
  protected function setGitLabCiCdVariables(array $project, ?string $cloud_application_uuid, $cloud_key, $cloud_secret, string $project_access_token_name, $project_access_token): void {
    $this->io->writeln("Setting GitLab CI/CD variables for {$project['path_with_namespace']}..");
    $gitlab_cicd_variables = [
      [
        'key' => 'ACQUIA_APPLICATION_UUID',
        'value' => $cloud_application_uuid,
        'masked' => FALSE,
        'protected' => FALSE
      ],
      [
        'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
        'value' => $cloud_key,
        'masked' => FALSE,
        'protected' => FALSE
      ],
      [
        'key' => 'ACQUIA_CLOUD_API_TOKEN_SECRET',
        'value' => $cloud_secret,
        'masked' => FALSE,
        'protected' => FALSE
      ],
      [
        'key' => 'ACQUIA_GLAB_TOKEN_NAME',
        'value' => $project_access_token_name,
        'masked' => FALSE,
        'protected' => FALSE
      ],
      [
        'key' => 'ACQUIA_GLAB_TOKEN_SECRET',
        'value' => $project_access_token,
        'masked' => TRUE,
        'protected' => FALSE
      ],
      [
        'key' => 'ACQUIA_CLOUD_SSH_KEY',
        'value' => $this->localMachineHelper->readFile($this->privateSshKeyFilepath),
        'masked' => FALSE,
        'protected' => FALSE
      ],
      [
        'key' => 'SSH_PASSPHRASE',
        'value' => $this->getPassPhraseFromFile(),
        'masked' => FALSE,
        'protected' => TRUE
      ],
    ];

    $gitlab_cicd_existing_variables = $this->gitLabClient->projects()
      ->variables($project['id']);
    $gitlab_cicd_existing_variables_keyed = [];
    foreach ($gitlab_cicd_existing_variables as $variable) {
      $key = $variable['key'];
      $gitlab_cicd_existing_variables_keyed[$key] = $variable;
    }

    foreach ($gitlab_cicd_variables as $variable) {
      $this->checklist->addItem("Setting CI/CD variable <comment>{$variable['key']}</comment>");
      if (!array_key_exists($variable['key'], $gitlab_cicd_existing_variables_keyed)) {
        $this->gitLabClient->projects()
          ->addVariable($project['id'], $variable['key'], $variable['value'], $variable['protected'], NULL, ['masked' => $variable['masked']]);
      }
      else {
        $this->gitLabClient->projects()
          ->updateVariable($project['id'], $variable['key'], $variable['value'], $variable['protected'], NULL, ['masked' => $variable['masked']]);
      }
      $this->checklist->completePreviousItem();
    }
  }

  /**
   * @param array $project
   */
  protected function createScheduledPipeline(array $project): void {
    $this->io->writeln("Creating scheduled pipeline");
    $scheduled_pipeline_description = "Code Studio Automatic Updates";

    if (!$this->getGitLabScheduleByDescription($project, $scheduled_pipeline_description)) {
      $this->checklist->addItem("Creating scheduled pipeline <comment>$scheduled_pipeline_description</comment>");
      $this->gitLabClient->schedules()->create($project['id'], [
        'description' => $scheduled_pipeline_description,
        'ref' => $project['default_branch'],
        # Every Thursday at midnight.
        'cron' => '0 0 * * 4',
      ]);
      $this->checklist->completePreviousItem();
    }
    else {
      $this->checklist->addItem("Scheduled pipeline named <comment>$scheduled_pipeline_description</comment> already exists");
      $this->checklist->completePreviousItem();
    }
  }

}
