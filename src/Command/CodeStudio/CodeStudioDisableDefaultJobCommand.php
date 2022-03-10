<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\HttpClient\Builder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class CodeStudioDisableDefaultJobCommand.
 */
class CodeStudioDisableDefaultJobCommand extends CommandBase {

  /**
   * @var string
   */
  private $gitlabHost;

  /**
   * @var string
   */
  private $gitlabToken;

  /**
   * @var \Gitlab\Client
   */
  private $gitLabClient;

  /**
   * @var array
   */
  private $gitLabAccount;

  protected static $defaultName = 'codestudio:disable-default-job';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Disable default jobs provided by the Code Studio.')
      ->addOption('gitlab-project-id', NULL, InputOption::VALUE_REQUIRED, 'The project ID (an integer) of the GitLab project to configure.')
      ->addOption('gitlab-token', NULL, InputOption::VALUE_REQUIRED, 'The GitLab personal access token that will be used to communicate with the GitLab instance')
      ->addOption('gitlab-project-job', NULL, InputOption::VALUE_REQUIRED, 'The default code studio job you want to disable');
    $this->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->gitlabHost = $this->getGitLabHost();
    $this->gitlabToken = $this->getGitLabToken($this->gitlabHost);
    $this->getGitLabClient();
    try {
      $this->gitLabAccount = $this->gitLabClient->users()->me();
    }
    catch (RuntimeException $exception) {
      $this->io->error([
        "Unable to authenticate with Code Studio",
        "Did you set a valid token with the <options=bold>api</> and <options=bold>write_repository</> scopes?",
        "Try running `glab auth login` to re-authenticate.",
        "Then try again.",
      ]);
      return 1;
    }

    $allGitLabProjects = $this->gitLabClient->projects()->all();
    // Prepare the list of project where user is allowed
    // to add / update variables.
    $allowedProjects = array_filter($allGitLabProjects, function ($project) {
      return (
        // 60 is the access level for the maintainer and 40 is admin access level.
        // Referred from https://docs.gitlab.com/ee/api/protected_branches.html
        // Only admin and maintainer access can manage variables.
        // Referred from https://docs.gitlab.com/ee/user/permissions.html
        isset($project['permissions']['project_access']['access_level'])
        && ($project['permissions']['project_access']['access_level'] == '60'
        || $project['permissions']['project_access']['access_level'] == '40')
      );
    });

    // If there are no project available for the user.
    if (count($allowedProjects) === 0) {
      $this->io->error('There are no code studio projects available for your account.');
      return 1;
    }

    $list = [];
    foreach ($allowedProjects as $allowedProject) {
      $list[$allowedProject['id']] = $allowedProject['name'];
    }

    $project_id = NULL;
    if ($input->getOption('gitlab-project-id')) {
      $project_id = $this->input->getOption('gitlab-project-id');
      // If project id provided by user is not valid.
      if (!isset($list[$project_id])) {
        $this->io->error('Project id provided is not valid. Please check the project id.');
        return 1;
      }
    }

    // If user not providing project id in option.
    if (!$project_id) {
      // Prepare list of projects to ask from the user.
      $project_choice_labels = array_values($list);
      $question = new ChoiceQuestion('Please select a Cloud Platform subscription',
        $project_choice_labels,
        $project_choice_labels[0],
      );
      $choice_id = $this->io->askQuestion($question);
      $project_id = array_search($choice_id, $list, TRUE);
    }

    // Variables list for Code Studio default jobs
    // https://docs.acquia.com/code-studio/configuring/modifying-default-job/#enabling-or-disabling-a-default-code-studio-job
    $variables_list = [
      'ACQUIA_JOBS_BUILD_DRUPAL',
      'ACQUIA_JOBS_TEST_DRUPAL',
      'ACQUIA_TASKS_INSTALL_DRUPAL',
      'ACQUIA_JOBS_DEPLOY_BRANCH',
      'ACQUIA_JOBS_DEPLOY_TAG',
      'ACQUIA_JOBS_CREATE_CDE',
      'ACQUIA_JOBS_DEPRECATED_UPDATE',
      'ACQUIA_JOBS_COMPOSER_UPDATE',
      'SAST_DISABLED',
    ];

    $variable = NULL;
    if ($input->getOption('gitlab-project-job')) {
      $variable = $this->input->getOption('gitlab-project-job');
      // If job provided by user is not valid.
      if (!in_array($variable, $variables_list)) {
        $this->io->error('Variable provided is not valid.');
        return 1;
      }
    }

    if (!$variable) {
      $question = new ChoiceQuestion('Please select the Code Studio default job you want to disable',
        $variables_list,
        $variables_list[0],
      );
      $choice_id = $this->io->askQuestion($question);
      $variable = array_search($choice_id, $variables_list, TRUE);
    }

    // Check if the variable already exists or not.
    $all_project_variables = $this->gitLabClient->projects()->variables($project_id);
    $is_variable_exist = FALSE;
    foreach (array_values($all_project_variables) as $project_variable) {
      if ($project_variable['key'] === $variable) {
        $is_variable_exist = TRUE;
        break;
      }
    }

    try {
      // If  variable not exists in project, create it.
      if (!$is_variable_exist) {
        $this->gitLabClient->projects()->addVariable($project_id, $variable, 'false');
      } else {
        // If variable already exists, updating the variable.
        $this->gitLabClient->projects()->updateVariable($project_id, $variable, 'true');
      }
    }
    catch (RuntimeException $exception) {
      $this->io->error([
        "Unable to update the job",
      ]);
      // Log the error for debugging purpose.
      $this->logger->debug('Error @error while updating/creating the job @variable for the project @project project id @project_id', [
        '@error' => $exception->getMessage(),
        '@project' => $list[$project_id],
        '@project_id' => $project_id,
        '@variable' => $variable,
      ]);
      return 1;
    }

    $this->io->info("Job `{$variable}` is disabled for the project `{$list[$project_id]}`");
    return 0;
  }

  /**
   * @return \Gitlab\Client
   */
  protected function getGitLabClient(): Client {
    if (!isset($this->gitLabClient)) {
      $gitlab_client = new Client(new Builder(new \GuzzleHttp\Client()));
      $gitlab_client->setUrl('https://' . $this->gitlabHost);
      $gitlab_client->authenticate($this->gitlabToken, Client::AUTH_OAUTH_TOKEN);
      $this->setGitLabClient($gitlab_client);
    }
    return $this->gitLabClient;
  }

  /**
   * @param Client $client
   */
  public function setGitLabClient($client) {
    $this->gitLabClient = $client;
  }

  /**
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
      $this->io->error([
        "Unable to authenticate with Code Studio",
        "Did you set a valid token with the <options=bold>api</> and <options=bold>write_repository</> scopes?",
        "Try running `glab auth login` to re-authenticate.",
        "Then try again.",
      ]);

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

}
