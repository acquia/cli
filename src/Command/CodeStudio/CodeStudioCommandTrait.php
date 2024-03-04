<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Response\ApplicationResponse;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\Exception\ValidationFailedException;
use Gitlab\HttpClient\Builder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\String\Slugger\AsciiSlugger;

trait CodeStudioCommandTrait {

  protected string $gitLabToken;

  protected string $gitLabHost;

  protected Client $gitLabClient;

  /**
   * @var array<mixed>
   */
  protected array $gitLabAccount;

  private string $gitLabProjectDescription;

  /**
   * Getting the gitlab token from user.
   */
  private function getGitLabToken(string $gitlabHost): string {
    if ($this->input->getOption('gitlab-token')) {
      return $this->input->getOption('gitlab-token');
    }
    if (!$this->localMachineHelper->commandExists('glab')) {
      throw new AcquiaCliException("Install glab to continue: https://gitlab.com/gitlab-org/cli#installation");
    }
    $process = $this->localMachineHelper->execute([
      'glab',
      'config',
      'get',
      'token',
      '--host=' . $gitlabHost,
    ], NULL, NULL, FALSE);
    if ($process->isSuccessful() && trim($process->getOutput())) {
      return trim($process->getOutput());
    }

    $this->io->writeln([
      "",
      "You must first authenticate with Code Studio by creating a personal access token:",
      "* Visit https://$gitlabHost/-/profile/personal_access_tokens",
      "* Create a token and grant it both <comment>api</comment> and <comment>write repository</comment> scopes",
      "* Copy the token to your clipboard",
      "* Run <comment>glab auth login --hostname=$gitlabHost</comment> and paste the token when prompted",
      "* Try this command again.",
    ]);

    throw new AcquiaCliException("Could not determine GitLab token");
  }

  /**
   * Getting gitlab host from user.
   */
  private function getGitLabHost(): string {
    // If hostname is available as argument, use that.
    if ($this->input->hasOption('gitlab-host-name')
     && $this->input->getOption('gitlab-host-name')) {
      return $this->input->getOption('gitlab-host-name');
    }
    if (!$this->localMachineHelper->commandExists('glab')) {
      throw new AcquiaCliException("Install glab to continue: https://gitlab.com/gitlab-org/cli#installation");
    }
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
    $urlParts = parse_url($output);
    if (!array_key_exists('scheme', $urlParts) && !array_key_exists('host', $urlParts)) {
      // $output looks like code.cloudservices.acquia.io.
      return $output;
    }
    // $output looks like http://code.cloudservices.acquia.io/.
    return $urlParts['host'];
  }

  private function getGitLabClient(): Client {
    if (!isset($this->gitLabClient)) {
      $gitlabClient = new Client(new Builder(new \GuzzleHttp\Client()));
      $gitlabClient->setUrl('https://' . $this->gitLabHost);
      $gitlabClient->authenticate($this->gitLabToken, Client::AUTH_OAUTH_TOKEN);
      $this->setGitLabClient($gitlabClient);
    }
    return $this->gitLabClient;
  }

  public function setGitLabClient(Client $client): void {
    $this->gitLabClient = $client;
  }

  private function writeApiTokenMessage(InputInterface $input): void {
    // Get Cloud access tokens.
    if (!$input->getOption('key') || !$input->getOption('secret')) {
      $tokenUrl = 'https://cloud.acquia.com/a/profile/tokens';
      $this->io->writeln([
        "",
        "This will configure AutoDevOps for a Code Studio project using credentials",
        "(an API Token and SSH Key) belonging to your current Acquia Cloud Platform user account.",
        "Before continuing, make sure that you're logged into the right Acquia Cloud Platform user account.",
        "",
        "<comment>Typically this command should only be run once per application</comment>",
        "but if your Cloud Platform account is deleted in the future, the Code Studio project will",
        "need to be re-configured using a different user account.",
        "",
        "<options=bold>To begin, visit this URL and create a new API Token for Code Studio to use:</>",
        "<href=$tokenUrl>$tokenUrl</>",
      ]);
    }
  }

  protected function validateEnvironment(): void {
    if (!empty(self::isAcquiaCloudIde()) && !getenv('GITLAB_HOST')) {
      throw new AcquiaCliException('The GITLAB_HOST environment variable must be set or the `--gitlab-host-name` option must be passed.');
    }
  }

  private function authenticateWithGitLab(): void {
    $this->validateEnvironment();
    $this->gitLabHost = $this->getGitLabHost();
    $this->gitLabToken = $this->getGitLabToken($this->gitLabHost);
    $this->getGitLabClient();
    try {
      $this->gitLabAccount = $this->gitLabClient->users()->me();
    }
    catch (RuntimeException $exception) {
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
   * @return array<mixed>
   */
  private function determineGitLabProject(ApplicationResponse $cloudApplication): array {
    // Use command option.
    if ($this->input->getOption('gitlab-project-id')) {
      $id = $this->input->getOption('gitlab-project-id');
      return $this->gitLabClient->projects()->show($id);
    }
    // Search for existing project that matches expected description pattern.
    $projects = $this->gitLabClient->projects()->all(['search' => $cloudApplication->uuid]);
    if ($projects) {
      if (count($projects) == 1) {
        return reset($projects);
      }

      return $this->promptChooseFromObjectsOrArrays(
        $projects,
        'id',
        'path_with_namespace',
        "Found multiple projects that could match the {$cloudApplication->name} application. Choose which one to configure."
      );
    }
    // Prompt to create project.
    $this->io->writeln([
      "",
      "Could not find any existing Code Studio project for Acquia Cloud Platform application <comment>{$cloudApplication->name}</comment>.",
      "Searched for UUID <comment>{$cloudApplication->uuid}</comment> in project descriptions.",
    ]);
    $createProject = $this->io->confirm('Would you like to create a new Code Studio project? If you select "no" you may choose from a full list of existing projects.');
    if ($createProject) {
      return $this->createGitLabProject($cloudApplication);
    }
    // Prompt to choose from full list, regardless of description.
    return $this->promptChooseFromObjectsOrArrays(
      $this->gitLabClient->projects()->all(),
      'id',
      'path_with_namespace',
      "Choose a Code Studio project to configure for the {$cloudApplication->name} application"
    );
  }

  /**
   * @return array<mixed>
   */
  private function createGitLabProject(ApplicationResponse $cloudApplication): array {
    $userGroups = $this->gitLabClient->groups()->all([
      'all_available' => TRUE,
      'min_access_level' => 40,
    ]);
    $parameters = $this->getGitLabProjectDefaults();
    if ($userGroups) {
      $userGroups[] = $this->gitLabClient->namespaces()->show($this->gitLabAccount['username']);
      $projectGroup = $this->promptChooseFromObjectsOrArrays($userGroups, 'id', 'path', 'Choose which group this new project should belong to:');
      $parameters['namespace_id'] = $projectGroup['id'];
    }

    $slugger = new AsciiSlugger();
    $projectName = (string) $slugger->slug($cloudApplication->name);
    $project = $this->gitLabClient->projects()->create($projectName, $parameters);
    try {
      $this->gitLabClient->projects()
        ->uploadAvatar($project['id'], __DIR__ . '/drupal_icon.png');
    }
    catch (ValidationFailedException) {
      $this->io->warning("Failed to upload project avatar");
    }
    $this->io->success("Created {$project['path_with_namespace']} project in Code Studio.");

    return $project;
  }

  private function setGitLabProjectDescription(mixed $cloudApplicationUuid): void {
    $this->gitLabProjectDescription = "Source repository for Acquia Cloud Platform application <comment>$cloudApplicationUuid</comment>";
  }

  /**
   * @return array<mixed>
   */
  private function getGitLabProjectDefaults(): array {
    return [
      'container_registry_access_level' => 'disabled',
      'description' => $this->gitLabProjectDescription,
      'topics' => 'Acquia Cloud Application',
    ];
  }

  /**
   * Add gitlab options to the command.
   *
   * @return $this
   */
  private function acceptGitlabOptions(): static {
    $this->addOption('gitlab-token', NULL, InputOption::VALUE_REQUIRED, 'The GitLab personal access token that will be used to communicate with the GitLab instance')
      ->addOption('gitlab-project-id', NULL, InputOption::VALUE_REQUIRED, 'The project ID (an integer) of the GitLab project to configure.')
      ->addOption('gitlab-host-name', NULL, InputOption::VALUE_REQUIRED, 'The GitLab hostname.');
    return $this;
  }

}
