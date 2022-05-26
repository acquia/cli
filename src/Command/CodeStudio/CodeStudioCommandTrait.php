<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Response\ApplicationResponse;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\HttpClient\Builder;
use Symfony\Component\Console\Input\InputInterface;

trait CodeStudioCommandTrait {

  /**
   * @var string
   */
  protected $gitLabToken;

  /**
   * @var string
   */
  protected $gitLabHost;

  /**
   * @var \Gitlab\Client
   */
  protected $gitLabClient;

  /**
   * @var array
   */
  protected $gitLabAccount;

  /**
   * @var string
   */
  private $gitLabProjectDescription;

  /**
   * Getting the gitlab token from user.
   * @param string $gitlab_host
   *
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getGitLabToken(string $gitlab_host): string {
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
   * Getting gitlab host from user.
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getGitLabHost(): string {
    $process = $this->localMachineHelper->execute([
      'glab',
      'config',
      'get',
      'host',
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Could not determine GitLab host: {error_message}", ['error_message' => $process->getErrorOutput()]);
    }
    $output = trim($process->getOutput());
    $url_parts = parse_url($output);
    if (!array_key_exists('scheme', $url_parts) && !array_key_exists('host', $url_parts)) {
      // $output looks like code.cloudservices.acquia.io.
      return $output;
    }
    // $output looks like http://code.cloudservices.acquia.io/.
    return $url_parts['host'];
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
        throw new AcquiaCliException("The Acquia Cloud account {account} does not have the required '{name}' permission. Please add the permissions to this user or use an API Token belonging to a different Acquia Cloud user.", [
          'account' => $account->mail,
          'name' => $name
        ]);
      }
    }
  }

  /**
   * @return \Gitlab\Client
   */
  protected function getGitLabClient(): Client {
    if (!isset($this->gitLabClient)) {
      $gitlab_client = new Client(new Builder(new \GuzzleHttp\Client()));
      $gitlab_client->setUrl('https://' . $this->gitLabHost);
      $gitlab_client->authenticate($this->gitLabToken, Client::AUTH_OAUTH_TOKEN);
      $this->setGitLabClient($gitlab_client);
    }
    return $this->gitLabClient;
  }

  /**
   * @param Client $client
   */
  public function setGitLabClient(Client $client) {
    $this->gitLabClient = $client;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   */
  protected function writeApiTokenMessage(InputInterface $input): void {
    // Get Cloud access tokens.
    if (!$input->getOption('key') || !$input->getOption('secret')) {
      $token_url = 'https://cloud.acquia.com/a/profile/tokens';
      $this->io->writeln([
        "",
        "This will configure AutoDevOps for a Code Studio project using credentials",
        "(an API Token and SSH Key) belonging to your current Acquia Cloud user account.",
        "Before continuing, make sure that you're logged into the right Acquia Cloud Platform user account.",
        "",
        "<comment>Typically this command should only be run once per application</comment>",
        "but if your Cloud Platform account is deleted in the future, the Code Studio project will",
        "need to be re-configured using a different user account.",
        "",
        "<options=bold>To begin, visit this URL and create a new API Token for Code Studio to use:</>",
        "<href=$token_url>$token_url</>",
      ]);
    }
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validateEnvironment() {
    if (self::isAcquiaCloudIde() && !getenv('GITLAB_HOST')) {
      throw new AcquiaCliException('The GITLAB_HOST environment variable must be set or the `--gitlab-host-name` option must be passed.');
    }
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function authenticateWithGitLab(): void {
    $this->gitLabHost = $this->getGitLabHost();
    $this->gitLabToken = $this->getGitLabToken($this->gitLabHost);
    $this->getGitLabClient();
    try {
      $this->gitLabAccount = $this->gitLabClient->users()->me();
    } catch (RuntimeException $exception) {
      $this->io->error([
        "Unable to authenticate with Code Studio",
        "Did you set a valid token with the <options=bold>api</> and <options=bold>write_repository</> scopes?",
        "Try running `glab auth login` to re-authenticate.",
        "Alternatively,  pass the <options=bold>--gitlab-token</> option.",
        "Then try again.",
      ]);
      throw new AcquiaCliException("Unable to authenticate with Code Studio");
    }
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
          $projects,
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
        "Could not find any existing Code Studio project for Acquia Cloud Platform application <comment>{$cloud_application->name}</comment>.",
        "Searched for UUID <comment>{$cloud_application->uuid}</comment> in project descriptions.",
      ]);
      $create_project = $this->io->confirm('Would you like to create a new Code Studio project? If you select "no" you may choose from a full list of existing projects.');
      if ($create_project) {
        return $this->createGitLabProject($cloud_application);
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
   *
   */
  protected function setGitLabProjectDescription($cloud_application_uuid): void {
    $this->gitLabProjectDescription = "Source repository for Acquia Cloud Platform application <comment>$cloud_application_uuid</comment>";
  }

  /**
   * @param string|null $cloud_application_uuid
   * @param string|null $cloud_key
   * @param string|null $cloud_secret
   * @param string|null $project_access_token_name
   * @param string|null $project_access_token
   *
   * @return array[]
   */
  public static function getGitLabCiCdVariableDefaults(?string $cloud_application_uuid, ?string $cloud_key, ?string $cloud_secret, ?string $project_access_token_name, ?string $project_access_token): array {
    return [
      [
        'key' => 'ACQUIA_APPLICATION_UUID',
        'value' => $cloud_application_uuid,
        'masked' => FALSE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
        'value' => $cloud_key,
        'masked' => FALSE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_CLOUD_API_TOKEN_SECRET',
        'value' => $cloud_secret,
        'masked' => FALSE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_GLAB_TOKEN_NAME',
        'value' => $project_access_token_name,
        'masked' => FALSE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_GLAB_TOKEN_SECRET',
        'value' => $project_access_token,
        'masked' => TRUE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
    ];
  }

}
