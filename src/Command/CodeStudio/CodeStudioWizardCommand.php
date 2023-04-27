<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\WizardCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Account;
use Gitlab\Exception\ValidationFailedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CodeStudioWizardCommand extends WizardCommandBase {

  use CodeStudioCommandTrait;

  protected static $defaultName = 'codestudio:wizard';

  private Checklist $checklist;

  protected function configure(): void {
    $this->setDescription('Create and/or configure a new Code Studio project for a given Acquia Cloud application')
      ->addOption('key', NULL, InputOption::VALUE_REQUIRED, 'The Cloud Platform API token that Code Studio will use')
      ->addOption('secret', NULL, InputOption::VALUE_REQUIRED, 'The Cloud Platform API secret that Code Studio will use')
      ->addOption('gitlab-token', NULL, InputOption::VALUE_REQUIRED, 'The GitLab personal access token that will be used to communicate with the GitLab instance')
      ->addOption('gitlab-project-id', NULL, InputOption::VALUE_REQUIRED, 'The project ID (an integer) of the GitLab project to configure.')
      ->addOption('gitlab-host-name', NULL, InputOption::VALUE_REQUIRED, 'The GitLab hostname.')
      ->setAliases(['cs:wizard']);
    $this->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->checklist = new Checklist($output);
    $this->authenticateWithGitLab();
    $this->writeApiTokenMessage($input);
    $cloud_key = $this->determineApiKey();
    $cloud_secret = $this->determineApiSecret();
    // We may already be authenticated with Acquia Cloud Platform via a refresh token.
    // But, we specifically need an API Token key-pair of Code Studio.
    // So we reauthenticate to be sure we're using the provided credentials.
    $this->reAuthenticate($cloud_key, $cloud_secret, $this->cloudCredentials->getBaseUri(), $this->cloudCredentials->getAccountsUri());
    $appUuid = $this->determineCloudApplication();

    // Get Cloud account.
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $account_adapter = new Account($acquia_cloud_client);
    $account = $account_adapter->get();
    $this->validateRequiredCloudPermissions(
      $acquia_cloud_client,
      $appUuid,
      $account,
      [
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
      ]
    );
    $this->setGitLabProjectDescription($appUuid);

    // Get Cloud application.
    $cloud_application = $this->getCloudApplication($appUuid);
    $project = $this->determineGitLabProject($cloud_application);

    $this->io->writeln([
      "",
      "This command will configure the Code Studio project <comment>{$project['path_with_namespace']}</comment> for automatic deployment to the",
      "Acquia Cloud Platform application <comment>{$cloud_application->name}</comment> (<comment>$appUuid</comment>)",
      "using credentials (API Token and SSH Key) belonging to <comment>{$account->mail}</comment>.",
      "",
      "If the <comment>{$account->mail}</comment> Cloud account is deleted in the future, this Code Studio project will need to be re-configured.",
    ]);
    $answer = $this->io->confirm('Do you want to continue?');
    if (!$answer) {
      return 0;
    }

    $project_access_token_name = 'acquia-codestudio';
    $project_access_token = $this->createProjectAccessToken($project, $project_access_token_name);
    $this->updateGitLabProject($project);
    $this->setGitLabCiCdVariables($project, $appUuid, $cloud_key, $cloud_secret, $project_access_token_name, $project_access_token);
    $this->createScheduledPipeline($project);

    $this->io->success([
      "Successfully configured the Code Studio project!",
      "This project will now use Acquia's Drupal optimized AutoDevOps to build, test, and deploy your code automatically to Acquia Cloud Platform via CI/CD pipelines.",
      "You can visit it here:",
      $project['web_url'],
      "",
      "Next, you should use git to push code to your Code Studio project. E.g.,",
      "  git remote add codestudio {$project['http_url_to_repo']}",
      "  git push codestudio",
    ]);
    $this->io->note(["If the {$account->mail} Cloud account is deleted in the future, this Code Studio project will need to be re-configured."]);

    return 0;
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * @param array $project
   * @return array|null
   */
  private function getGitLabScheduleByDescription(array $project, string $scheduled_pipeline_description): ?array {
    $existing_schedules = $this->gitLabClient->schedules()->showAll($project['id']);
    foreach ($existing_schedules as $schedule) {
      if ($schedule['description'] == $scheduled_pipeline_description) {
        return $schedule;
      }
    }
    return NULL;
  }

  /**
   * @param array $project
   * @return array|null ?
   */
  private function getGitLabProjectAccessTokenByName(array $project, string $name): ?array {
    $existing_project_access_tokens = $this->gitLabClient->projects()->projectAccessTokens($project['id']);
    foreach ($existing_project_access_tokens as $key => $token) {
      if ($token['name'] == $name) {
        return $token;
      }
    }
    return NULL;
  }

  /**
   * @param array $project
   */
  private function createProjectAccessToken(array $project, string $project_access_token_name): string {
    $this->io->writeln("Creating project access token...");

    if ($existing_token = $this->getGitLabProjectAccessTokenByName($project, $project_access_token_name)) {
      $this->checklist->addItem("Deleting access token named <comment>$project_access_token_name</comment>");
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
  }

  /**
   * @param array $project
   */
  private function setGitLabCiCdVariables(array $project, string $cloud_application_uuid, string $cloud_key, string $cloud_secret, string $project_access_token_name, string $project_access_token): void {
    $this->io->writeln("Setting GitLab CI/CD variables for {$project['path_with_namespace']}..");
    $gitlab_cicd_variables = CodeStudioCiCdVariables::getDefaults($cloud_application_uuid, $cloud_key, $cloud_secret, $project_access_token_name, $project_access_token);
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
          ->addVariable($project['id'], $variable['key'], $variable['value'], $variable['protected'], NULL, ['masked' => $variable['masked'], 'variable_type' => $variable['variable_type']]);
      }
      else {
        $this->gitLabClient->projects()
          ->updateVariable($project['id'], $variable['key'], $variable['value'], $variable['protected'], NULL, ['masked' => $variable['masked'], 'variable_type' => $variable['variable_type']]);
      }
      $this->checklist->completePreviousItem();
    }
  }

  /**
   * @param array $project
   */
  private function createScheduledPipeline(array $project): void {
    $this->io->writeln("Creating scheduled pipeline");
    $scheduled_pipeline_description = "Code Studio Automatic Updates";

    if (!$this->getGitLabScheduleByDescription($project, $scheduled_pipeline_description)) {
      $this->checklist->addItem("Creating scheduled pipeline <comment>$scheduled_pipeline_description</comment>");
      $pipeline = $this->gitLabClient->schedules()->create($project['id'], [
        # Every Thursday at midnight.
        'cron' => '0 0 * * 4',
        'description' => $scheduled_pipeline_description,
        'ref' => $project['default_branch'],
      ]);
      $this->gitLabClient->schedules()->addVariable($project['id'], $pipeline['id'], [
        'key' => 'ACQUIA_JOBS_DEPRECATED_UPDATE',
        'value' => 'true',
      ]);
      $this->gitLabClient->schedules()->addVariable($project['id'], $pipeline['id'], [
        'key' => 'ACQUIA_JOBS_COMPOSER_UPDATE',
        'value' => 'true',
      ]);
    }
    else {
      $this->checklist->addItem("Scheduled pipeline named <comment>$scheduled_pipeline_description</comment> already exists");
    }
    $this->checklist->completePreviousItem();
  }

  /**
   * @param array $project
   */
  private function updateGitLabProject(array $project): void {
    // Setting the description to match the known pattern will allow us to automatically find the project next time.
    if ($project['description'] !== $this->gitLabProjectDescription) {
      $this->gitLabClient->projects()->update($project['id'], $this->getGitLabProjectDefaults());
      try {
        $this->gitLabClient->projects()->uploadAvatar($project['id'], __DIR__ . '/drupal_icon.png');
      }
      catch (ValidationFailedException) {
        $this->io->warning("Failed to upload project avatar");
      }
    }
  }

  /**
   * Gets the default branch name for the deployment artifact.
   */
  protected function getCurrentBranchName(): string {
    $process = $this->localMachineHelper->execute([
      'git',
      'rev-parse',
      '--abbrev-ref',
      'HEAD',
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Could not determine current git branch");
    }
    return trim($process->getOutput());
  }

}
