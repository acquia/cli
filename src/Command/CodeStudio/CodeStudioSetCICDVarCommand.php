<?php

namespace Acquia\Cli\Command\CodeStudio;

use Gitlab\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class CodeStudioSetCICDVarCommand.
 */
class CodeStudioSetCICDVarCommand extends GitLabCommandBase {

  /**
   * @var array
   */
  protected $gitLabAccount;

  protected static $defaultName = 'codestudio:set-cicd-var';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Set the CI/CD variables for the Code Studio project.')
      ->addOption('gitlab-project-id', NULL, InputOption::VALUE_REQUIRED, 'The project ID (an integer) of the GitLab project to configure.')
      ->addOption('gitlab-token', NULL, InputOption::VALUE_REQUIRED, 'The GitLab personal access token that will be used to communicate with the GitLab instance.')
      ->addOption('gitlab-host-name', NULL, InputOption::VALUE_REQUIRED, 'The GitLab hostname.')
      ->addOption('gitlab-cicd-var', NULL, InputOption::VALUE_REQUIRED, 'The Code Studio CI/CD variable which needs to set/update.')
      ->addOption('gitlab-cicd-var-value', NULL, InputOption::VALUE_REQUIRED, 'The value of the CI/CD variable which needs to be updated.')
      ->addUsage(self::getDefaultName() . '  --gitlab-project-id=<codeStudioProjectId> --gitlab-token=<codeStudioToken> --gitlab-host-name=<codeStudiohostName> --gitlab-cicd-var=<CICD_VAR> --gitlab-cicd-var-value=`<CICD_VAR_VALUE`>');

    $supported_variables_list = array_keys($this->getSupportedVariables());
    $supported_variables = implode(PHP_EOL, $supported_variables_list);
    $this->setHelp('This command allows to update the CI/CD variable for a given Code Studio project.' . PHP_EOL . PHP_EOL
      . 'Below are the supported list of variables' . PHP_EOL
      . '-----------------------------------------' . PHP_EOL
      . $supported_variables . PHP_EOL);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    try {
      $this->gitLabAccount = $this->getGitLabClient()->users()->me();
    }
    catch (RuntimeException $exception) {
      $this->io->error([
        "Unable to authenticate with Code Studio",
        "Did you set a valid token with the <options=bold>api</> and <options=bold>write_repository</> scopes?",
        "Try running `glab auth login` to re-authenticate.",
        "Then try again.",
        "Or run <comment>codestudio:set-cicd-var</comment> command with <comment>--gitlab-token</comment> option.",
      ]);
      return 1;
    }

    // Only considering those project where user is owner for now.
    // @todo: Check if there is something for environment variable permission.
    $allowedProjects = $this->gitLabClient->projects()->all(['owned' => TRUE]);

    // If there are no projects available for the user.
    if (count($allowedProjects) === 0) {
      $this->io->error('There are no Code Studio projects available for your account.');
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
        $this->io->error("The project id {$project_id} provided is either invalid or you do not have sufficient access to set variables for it.");
        return 1;
      }
    }

    // If the user has not provided the project id as an option.
    if (!$project_id) {
      // Prepare list of projects to ask from the user.
      $project_choice_labels = array_values($list);
      $question = new ChoiceQuestion('Please select a Code Studio project',
        $project_choice_labels,
        $project_choice_labels[0],
      );
      $choice_id = $this->io->askQuestion($question);
      $project_id = array_search($choice_id, $list, TRUE);
    }

    // List of CI/CD variables.
    $variables_list = $this->getSupportedVariables();
    $variable_names = array_keys($variables_list);

    $variable = NULL;
    if ($input->getOption('gitlab-cicd-var')) {
      $variable = $this->input->getOption('gitlab-cicd-var');
      // If job provided by user is not valid.
      if (!in_array($variable, $variable_names)) {
        $this->io->error("The variable {$variable} is not supported by this command.");
        $this->io->table(['Code Studio CI/CD supported variable are'], array_chunk($variable_names, 1));
        return 1;
      }
    }

    if (!$variable) {
      $question = new ChoiceQuestion('Please select the Code Studio CI/CD variable that you want to update.',
        $variable_names,
        $variable_names[0],
      );
      $variable = $this->io->askQuestion($question);
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

    $variable_value = '';
    if ($this->input->getOption('gitlab-cicd-var-value')) {
      // If user has provided the variable value as an option.
      $variable_value = $this->input->getOption('gitlab-cicd-var-value');
    }

    // If variable value is not provided, ask user to enter it.
    if (!$variable_value) {
      $variable_val_question = new Question('Please enter the CI/CD variable value');
      $variable_value = $this->io->askQuestion($variable_val_question);
    }

    // Some variables only support true/false value.
    // If value provided for those variables is not true/false,
    // we ignore that value.
    if (in_array($variables_list[$variable], ['true', 'false']) && !in_array($variable_value, ['true', 'false'])) {
      $this->io->error("The variable {$variable} only supports either `true` or `false` value.");
      return 1;
    }

    try {
      // If  variable not exists in project, create it.
      if (!$is_variable_exist) {
        $this->gitLabClient->projects()->addVariable($project_id, $variable, $variable_value);
      } else {
        // If variable already exists, updating the variable.
        $this->gitLabClient->projects()->updateVariable($project_id, $variable, $variable_value);
      }
    }
    catch (RuntimeException $exception) {
      $this->io->error([
        "Unable to update the variable {$variable} with value {$variable_value}",
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

    $this->io->info("Variable `{$variable}` is updated for the project `{$list[$project_id]}` with value '{$variable_value}'");
    return 0;
  }

  /**
   * {@inheritDoc}
   */
  protected function getGitLabHost(): string {
    if ($this->input->getOption('gitlab-host-name')) {
      return $this->input->getOption('gitlab-host-name');
    }

    return parent::getGitLabHost();
  }

  /**
   * List of supported CI/CD variables with default values.
   *
   * @return string[]
   */
  protected function getSupportedVariables() {
    return [
      'ACQUIA_JOBS_BUILD_DRUPAL' => 'true',
      'ACQUIA_JOBS_TEST_DRUPAL' => 'true',
      'ACQUIA_TASKS_SETUP_DRUPAL' => 'false',
      'ACQUIA_TASKS_PHPCS' => 'true',
      'ACQUIA_TASKS_PHPSTAN' => 'true',
      'ACQUIA_TASKS_DRUTINY' => 'false',
      'ACQUIA_TASKS_PHPUNIT' => 'true',
      'ACQUIA_TASKS_SETUP_DRUPAL_CONFIG_IMPORT' => 'true',
      'ACQUIA_JOBS_CREATE_BRANCH_ARTIFACT' => 'true',
      'ACQUIA_JOBS_CREATE_TAG_ARTIFACT' => 'true',
      'ACQUIA_JOBS_DEPLOY_TAG' => 'false',
      'ACQUIA_JOBS_CREATE_CDE' => 'true',
      'ACQUIA_JOBS_DEPRECATED_UPDATE' => 'true',
      'ACQUIA_JOBS_COMPOSER_UPDATE' => 'true',
      'ACQUIA_JOBS_BEAUTIFY_CODE' => 'false',
      'SAST_EXCLUDED_PATHS' => 'spec, test, tests, tmp, node_modules, vendor, contrib, core',
      'SECRET_DETECTION_EXCLUDED_PATHS' => 'spec, tmp, node_modules, vendor, contrib, core',
      'ACQUIA_CUSTOM_CODE_DIRS' => 'docroot/modules/custom docroot/themes/custom docroot/profiles/custom',
    ];
  }

}
