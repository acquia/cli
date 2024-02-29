<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\WizardCommandBase;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Account;
use DateTime;
use Gitlab\Exception\ValidationFailedException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'codestudio:wizard', description: 'Create and/or configure a new Code Studio project for a given Cloud Platform application', aliases: ['cs:wizard'])]
final class CodeStudioWizardCommand extends WizardCommandBase {

  use CodeStudioCommandTrait;

  private Checklist $checklist;

  protected function configure(): void {
    $this
      ->addOption('key', NULL, InputOption::VALUE_REQUIRED, 'The Cloud Platform API token that Code Studio will use')
      ->addOption('secret', NULL, InputOption::VALUE_REQUIRED, 'The Cloud Platform API secret that Code Studio will use')
      ->addOption('gitlab-token', NULL, InputOption::VALUE_REQUIRED, 'The GitLab personal access token that will be used to communicate with the GitLab instance')
      ->addOption('gitlab-project-id', NULL, InputOption::VALUE_REQUIRED, 'The project ID (an integer) of the GitLab project to configure.')
      ->addOption('gitlab-host-name', NULL, InputOption::VALUE_REQUIRED, 'The GitLab hostname.');
    $this->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->checklist = new Checklist($output);
    $this->authenticateWithGitLab();
    $this->writeApiTokenMessage($input);
    $cloudKey = $this->determineApiKey();
    $cloudSecret = $this->determineApiSecret();
    // We may already be authenticated with Acquia Cloud Platform via a refresh token.
    // But, we specifically need an API Token key-pair of Code Studio.
    // So we reauthenticate to be sure we're using the provided credentials.
    $this->reAuthenticate($cloudKey, $cloudSecret, $this->cloudCredentials->getBaseUri(), $this->cloudCredentials->getAccountsUri());
    $phpVersion = NULL;
    $nodeVersion = NULL;
    $projectType = $this->getListOfProjectType();
    $projectSelected = $this->io->choice('Select a project type', $projectType, $projectType[0]);

    switch ($projectSelected) {
      case "Drupal_project":
        $phpVersions = [
            'PHP_version_8.1' => "8.1",
            'PHP_version_8.2' => "8.2",
          ];
        $project = $this->io->choice('Select a PHP version', array_values($phpVersions), "8.1");
        $project = array_search($project, $phpVersions, TRUE);
        $phpVersion = $phpVersions[$project];
          break;
      case "Node_project":
        $nodeVersions = [
            'NODE_version_18.17.1' => "18.17.1",
            'NODE_version_20.5.1' => "20.5.1",
          ];
        $project = $this->io->choice('Select a NODE version', array_values($nodeVersions), "18.17.1");
        $project = array_search($project, $nodeVersions, TRUE);
        $nodeVersion = $nodeVersions[$project];
          break;
    }

    $appUuid = $this->determineCloudApplication();

    // Get Cloud account.
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $accountAdapter = new Account($acquiaCloudClient);
    $account = $accountAdapter->get();
    $this->validateRequiredCloudPermissions(
      $acquiaCloudClient,
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
    $cloudApplication = $this->getCloudApplication($appUuid);
    $project = $this->determineGitLabProject($cloudApplication);

    $this->io->writeln([
      "",
      "This command will configure the Code Studio project <comment>{$project['path_with_namespace']}</comment> for automatic deployment to the",
      "Acquia Cloud Platform application <comment>{$cloudApplication->name}</comment> (<comment>$appUuid</comment>)",
      "using credentials (API Token and SSH Key) belonging to <comment>{$account->mail}</comment>.",
      "",
      "If the <comment>{$account->mail}</comment> Cloud account is deleted in the future, this Code Studio project will need to be re-configured.",
    ]);
    $answer = $this->io->confirm('Do you want to continue?');
    if (!$answer) {
      return Command::SUCCESS;
    }

    $projectAccessTokenName = 'acquia-codestudio';
    $projectAccessToken = $this->createProjectAccessToken($project, $projectAccessTokenName);
    $this->updateGitLabProject($project);
    switch ($projectSelected) {
      case "Drupal_project":
        $this->setGitLabCiCdVariablesForPhpProject($project, $appUuid, $cloudKey, $cloudSecret, $projectAccessTokenName, $projectAccessToken, $phpVersion);
        break;
      case "Node_project":
        $this->setGitLabCiCdVariablesForNodeProject($project, $appUuid, $cloudKey, $cloudSecret, $projectAccessTokenName, $projectAccessToken, $nodeVersion);
        break;
    }
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

    return Command::SUCCESS;
  }

  /**
   * @param array $project
   * @return array<mixed>|null
   */
  private function getGitLabScheduleByDescription(array $project, string $scheduledPipelineDescription): ?array {
    $existingSchedules = $this->gitLabClient->schedules()->showAll($project['id']);
    foreach ($existingSchedules as $schedule) {
      if ($schedule['description'] == $scheduledPipelineDescription) {
        return $schedule;
      }
    }
    return NULL;
  }

  /**
   * @param array $project
   * @return array<mixed>|null ?
   */
  private function getGitLabProjectAccessTokenByName(array $project, string $name): ?array {
    $existingProjectAccessTokens = $this->gitLabClient->projects()->projectAccessTokens($project['id']);
    foreach ($existingProjectAccessTokens as $key => $token) {
      if ($token['name'] == $name) {
        return $token;
      }
    }
    return NULL;
  }

  /**
   * @return array<mixed>|null ?
   */
  private function getListOfProjectType(): ?array {
    $array = [
      'Drupal_project',
      'Node_project',
    ];
    return $array;
  }

  private function createProjectAccessToken(array $project, string $projectAccessTokenName): string {
    $this->io->writeln("Creating project access token...");

    if ($existingToken = $this->getGitLabProjectAccessTokenByName($project, $projectAccessTokenName)) {
      $this->checklist->addItem("Deleting access token named <comment>$projectAccessTokenName</comment>");
      $this->gitLabClient->projects()
            ->deleteProjectAccessToken($project['id'], $existingToken['id']);
      $this->checklist->completePreviousItem();
    }
    $this->checklist->addItem("Creating access token named <comment>$projectAccessTokenName</comment>");
    $projectAccessToken = $this->gitLabClient->projects()
          ->createProjectAccessToken($project['id'], [
          'expires_at' => new DateTime('+365 days'),
          'name' => $projectAccessTokenName,
          'scopes' => ['api', 'write_repository'],
        ]);
    $this->checklist->completePreviousItem();
    return $projectAccessToken['token'];
  }

  private function setGitLabCiCdVariablesForPhpProject(array $project, string $cloudApplicationUuid, string $cloudKey, string $cloudSecret, string $projectAccessTokenName, string $projectAccessToken, string $phpVersion): void {
    $this->io->writeln("Setting GitLab CI/CD variables for {$project['path_with_namespace']}..");
    $gitlabCicdVariables = CodeStudioCiCdVariables::getDefaultsForPhp($cloudApplicationUuid, $cloudKey, $cloudSecret, $projectAccessTokenName, $projectAccessToken, $phpVersion);
    $gitlabCicdExistingVariables = $this->gitLabClient->projects()
      ->variables($project['id']);
    $gitlabCicdExistingVariablesKeyed = [];
    foreach ($gitlabCicdExistingVariables as $variable) {
      $key = $variable['key'];
      $gitlabCicdExistingVariablesKeyed[$key] = $variable;
    }

    foreach ($gitlabCicdVariables as $variable) {
      $this->checklist->addItem("Setting CI/CD variable <comment>{$variable['key']}</comment>");
      if (!array_key_exists($variable['key'], $gitlabCicdExistingVariablesKeyed)) {
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

  private function setGitLabCiCdVariablesForNodeProject(array $project, string $cloudApplicationUuid, string $cloudKey, string $cloudSecret, string $projectAccessTokenName, string $projectAccessToken, string $nodeVersion): void {
    $this->io->writeln("Setting GitLab CI/CD variables for {$project['path_with_namespace']}..");
    $gitlabCicdVariables = CodeStudioCiCdVariables::getDefaultsForNode($cloudApplicationUuid, $cloudKey, $cloudSecret, $projectAccessTokenName, $projectAccessToken, $nodeVersion);
    $gitlabCicdExistingVariables = $this->gitLabClient->projects()
      ->variables($project['id']);
    $gitlabCicdExistingVariablesKeyed = [];
    foreach ($gitlabCicdExistingVariables as $variable) {
      $key = $variable['key'];
      $gitlabCicdExistingVariablesKeyed[$key] = $variable;
    }

    foreach ($gitlabCicdVariables as $variable) {
      $this->checklist->addItem("Setting CI/CD variable <comment>{$variable['key']}</comment>");
      if (!array_key_exists($variable['key'], $gitlabCicdExistingVariablesKeyed)) {
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

  private function createScheduledPipeline(array $project): void {
    $this->io->writeln("Creating scheduled pipeline");
    $scheduledPipelineDescription = "Code Studio Automatic Updates";

    if (!$this->getGitLabScheduleByDescription($project, $scheduledPipelineDescription)) {
      $this->checklist->addItem("Creating scheduled pipeline <comment>$scheduledPipelineDescription</comment>");
      $pipeline = $this->gitLabClient->schedules()->create($project['id'], [
        # Every Thursday at midnight.
        'cron' => '0 0 * * 4',
        'description' => $scheduledPipelineDescription,
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
      $this->checklist->addItem("Scheduled pipeline named <comment>$scheduledPipelineDescription</comment> already exists");
    }
    $this->checklist->completePreviousItem();
  }

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

}
