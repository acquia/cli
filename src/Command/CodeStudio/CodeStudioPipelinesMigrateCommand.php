<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CodeStudioPipelinesMigrateCommand.
 */
class CodeStudioPipelinesMigrateCommand extends CommandBase {

  use CodeStudioCommandTrait;

  protected static $defaultName = 'codestudio:pipelines-migrate';

  /**
   * @var string
   */
  private $appUuid;

  /**
   * @var array
   */
  private $migrateScriptData;

  /**
   * @var array
   */
  private $availableChoices = ['yes', 'no'];

  /**
   * @var string
   */
  private $filename;

  /**
   * @var string
   */
  private $defaultChoice = 'no';

  /**
   * @var string
   */
  private $projects;

  /**
   * @var \Acquia\Cli\Output\Checklist
   */
  private $checklist;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Migrate .acquia-pipeline.yml file to .gitlab-ci.yml file for a given Acquia Cloud application')
      ->addOption('key', NULL, InputOption::VALUE_REQUIRED, 'The Cloud Platform API token that Code Studio will use')
      ->addOption('secret', NULL, InputOption::VALUE_REQUIRED, 'The Cloud Platform API secret that Code Studio will use')
      ->addOption('gitlab-token', NULL, InputOption::VALUE_REQUIRED, 'The GitLab personal access token that will be used to communicate with the GitLab instance')
      ->addOption('gitlab-project-id', NULL, InputOption::VALUE_REQUIRED, 'The project ID (an integer) of the GitLab project to configure.')
      ->setAliases(['cs:pipelines-migrate']);
    $this->acceptApplicationUuid();
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
    $this->setMigrateScriptData([]);

    $this->checklist = new Checklist($output);
    $this->authenticateWithGitLab();
    $this->writeApiTokenMessage($input);
    $cloud_key = $this->determineApiKey($input, $output);
    $cloud_secret = $this->determineApiSecret($input, $output);
    // We may already be authenticated with Acquia Cloud Platform via a refresh token.
    // But, we specifically need an API Token key-pair of Code Studio.
    // So we reauthenticate to be sure we're using the provided credentials.
    $this->reAuthenticate($cloud_key, $cloud_secret, $this->cloudCredentials->getBaseUri());
    $this->appUuid = $this->determineCloudApplication();

    // Get Cloud account.
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $account_adapter = new Account($acquia_cloud_client);
    $account = $account_adapter->get();
    $this->validateRequiredCloudPermissions($acquia_cloud_client, $this->appUuid, $account);
    $this->setGitLabProjectDescription();

    // Get Cloud application.
    $cloud_application = $this->getCloudApplication($this->appUuid);
    $project = $this->determineGitLabProject($cloud_application);

    // Migrate acquia-pipeline file
    $this->getGitLabCiCdVariables($project);
    $this->validateCwdIsValidDrupalProject();
    $acquia_pipelines_file_contents = $this->getAcquiaPipelinesFileContents($project);
    $gitlab_ci_file_contents = $this->getGitLabCiFileTemplate();
    $this->migrateVariablesSection($acquia_pipelines_file_contents, $gitlab_ci_file_contents);
    $this->migrateBuildSection($acquia_pipelines_file_contents, $gitlab_ci_file_contents);
    $this->migratePostDeploySection($acquia_pipelines_file_contents, $gitlab_ci_file_contents);
    $this->io->success([
      "",
      "Migration completed successfully.",
      "Created .gitlab-ci.yml and removed acquia-pipeline.yml file.",
      "In order to run Pipeline, push .gitlab-ci.yaml to Main branch of Code Studio project.",
      "Check your pipeline is running in Code Studio for your project."
    ]);

    return 0;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    return FALSE;
  }

  /**
   * Check whether wizard command is executed by checking the env variable of codestudio project.
   * @param array $project
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getGitLabCiCdVariables(array $project) {
    $gitlab_cicd_variables = $this->getGitLabCiCdVariableDefaults(NULL, NULL, NULL, NULL, NULL);
    $gitlab_cicd_existing_variables = $this->gitLabClient->projects()->variables($project['id']);
    $existing_keys = array_column($gitlab_cicd_existing_variables, 'key');
    foreach ($gitlab_cicd_variables as $gitlab_cicd_variable) {
      if (array_search($gitlab_cicd_variable['key'], $existing_keys) === FALSE) {
        throw new AcquiaCliException("Code Studio CI/CD variable {$gitlab_cicd_variable['key']} is not configured properly");
      }
    }
  }

  /**
   * Check acquia-pipeline.yml file exists in the root repo and remove ci_config_path from codestudio project.
   *
   * @param array $project
   *
   * @return array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getAcquiaPipelinesFileContents(array $project): array {
    $pipelines_filepath_yml = Path::join($this->repoRoot, 'acquia-pipelines.yml');
    $pipelines_filepath_yaml = Path::join($this->repoRoot, 'acquia-pipelines.yaml');
    if ($this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yml) ||
      $this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yaml)
    ) {
      $this->gitLabClient->projects()->update($project['id'], ['ci_config_path' => '']);
      $filename = implode('', glob("acquia-pipelines.*"));
      $file_contents = file_get_contents($filename);
      return Yaml::parse($file_contents, Yaml::PARSE_OBJECT);
    }
    else {
      throw new AcquiaCliException("Missing 'acquia-pipelines.yml' file which is required to migrate the project to Code Studio.");
    }
  }

  /**
   * Migrating standard template to .gitlab-ci.yml file.
   */
  protected function getGitLabCiFileTemplate(): array {
    return [
        'include' => ['project' => 'acquia/standard-template', 'file' => '/gitlab-ci/Auto-DevOps.acquia.gitlab-ci.yml'],
    ];
  }

  // @todo Catch exception if any and throw error.

  /**
   * Migrating `variables` section to .gitlab-ci.yml file.
   */
  protected function migrateVariablesSection($acquia_pipelines_file_contents, &$gitlab_ci_file_contents) {
    if (array_key_exists('variables', $acquia_pipelines_file_contents)) {
      $variables_dump = Yaml::dump(['variables' => $acquia_pipelines_file_contents['variables']]);
      $remove_global = preg_replace('/global:/', '', $variables_dump);
      $variables_parse = Yaml::parse($remove_global);
      $gitlab_ci_file_contents = array_merge($gitlab_ci_file_contents, $variables_parse);
      $this->io->success([
        "Migrated `variables` section of acquia-pipelines.yml to .gitlab-ci.yml",
      ]);
    }
    else {
      $this->io->info([
        "Checked acquia-pipeline.yml file for `variables` section",
      ]);
    }
  }

  /**
   * Migrating build job to .gitlab-ci.yml file.
   */
  protected function migrateBuildSection($acquia_pipelines_file_contents, &$gitlab_ci_file_contents) {
    if (!array_key_exists('events', $acquia_pipelines_file_contents)) {
      return;
    }
    if (!array_key_exists('build', $acquia_pipelines_file_contents['events'])) {
      return;
    }
    if (!array_key_exists('steps', $acquia_pipelines_file_contents['events']['build'])) {
      return;
    }
    $pipelines_build_steps = $acquia_pipelines_file_contents['events']['build']['steps'];
    $code_studio_jobs = [];

    if ($pipelines_build_steps) {
      foreach ($pipelines_build_steps as $step_index => $step) {
        $script_name = array_keys($step)[0];
        if (!array_key_exists('script', $step[$script_name]) || empty($step[$script_name]['script'])) {
          continue;
        }
        foreach ($step[$script_name]['script'] as $command){
          if (strstr($command, 'composer install')) {
            $this->io->note([
              'Code Studio AutoDevOps will run `composer install` by default. Skipping migration of this command in your acquia-pipelines.yml file:',
              $command,
            ]);
            continue;
          }
          elseif (strstr($command, '${BLT_DIR}')) {
            $this->io->note([
              'Code Studio AutoDevOps will run BLT commands for you by default. Skipping migration of this command in your acquia-pipelines.yml file:',
              $command,
            ]);
            continue;
          }
          $code_studio_jobs[$script_name]['script'][] = $command;
        }
        $code_studio_jobs = $this->assignStage($code_studio_jobs, $script_name);
      }
      $this->io->success([
        "Completed migration of the build step in your acquia-pipelines.yml file",
      ]);
      $gitlab_ci_file_contents = array_merge($gitlab_ci_file_contents, $code_studio_jobs);
    }
    else {
      $this->io->writeln([
        "",
        "acquia-pipeline.yml file does not contain Build job to migrate",
      ]);
    }
  }

  /**
   * Assign build job its equivalent stages.
   *
   * @param array $code_studio_job
   * @param string $pipelines_script_name
   *
   * @return array
   */
  protected function assignStage(array $code_studio_job, string $pipelines_script_name): array {
    $script_to_stages_map = [
      'setup' => 'Build Drupal',
      'npm run build' => 'Build Drupal',
      'validate' => 'Test Drupal',
      'tests' => 'Test Drupal',
      'test'  => 'Test Drupal',
      'npm test' => 'Test Drupal',
      'artifact' => 'Deploy Drupal',
      'deploy' => 'Deploy Drupal',
    ];
    foreach ($script_to_stages_map as $script_name => $code_studio_stage) {
      if (strstr($pipelines_script_name, $script_name)) {
        $code_studio_job[$pipelines_script_name]['stage'] = $code_studio_stage;
      }
    }
    if (empty($code_studio_job[$pipelines_script_name]['stage'])) {
      // Default stage.
      $code_studio_job[$pipelines_script_name]['stage'] = 'Test Drupal';
    }
    return $code_studio_job;
  }

  /**
   * Creating .gitlab-ci.yml file.
   */
  protected function createGitLabCiFile(array $contents) {
    $gitlab_ci_filepath = Path::join($this->repoRoot, '.gitlab-ci.yml');
    $this->localMachineHelper->getFilesystem()->dumpFile($gitlab_ci_filepath, Yaml::dump($contents, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
  }

  /**
   * Migrating post build job to .gitlab-ci.yml file.
   */
  protected function migratePostDeploySection($acquia_pipelines_file_contents, $gitlab_ci_file_contents) {
    if (!array_key_exists('events', $acquia_pipelines_file_contents)) {
      return;
    }
    if (!array_key_exists('post-deploy', $acquia_pipelines_file_contents['events'])) {
      return;
    }
    if (!array_key_exists('steps', $acquia_pipelines_file_contents['events']['post-deploy'])) {
      return;
    }
    $pipelines_post_deploy_steps = $acquia_pipelines_file_contents['events']['post-deploy']['steps'];
    if ($pipelines_post_deploy_steps) {
      $response_launch_ode = NULL;
      foreach ($pipelines_post_deploy_steps as $keys => $values) {
        $keysvariable = array_keys($values)[0];
        if (empty($values[$keysvariable]['script'])) {
          continue;
        }
        foreach ($values[$keysvariable]['script'] as $scripting) {
          if (strstr ($scripting, 'launch_ode')) {
            if (empty($response_launch_ode)) {
              $ques = "launch_ode script is part of Code Studio Auto DevOps Pipeline, do you still want to migrate launch_ode script?";
              $response_launch_ode = $this->io->confirm($ques, FALSE) ? 'yes' : 'no';
            }
            if ($response_launch_ode == 'yes') {
              $arrayempty[$keysvariable]['script'][] = $scripting;
              continue;
            }
            unset($arrayempty[$keysvariable]);
            break;
          }
          else {
            $arrayempty[$keysvariable]['script'][] = $scripting;
          }
        }
        $arrayempty = $this->assignPostDeployStages($arrayempty, $keysvariable);
      }
      $this->mergePostDeployCode($arrayempty);
      $this->io->writeln([
        "",
        "Migration completed for Post-Deploy job section",
      ]);
    }
    else {
      $this->io->writeln([
        "",
        "acquia-pipeline.yml file does not contain Post-Deploy job to migrate",
      ]);
    }
  }

  /**
   * Assign post-build job its equivalent stages.
   *
   * @param array $arrayempty
   * @param string $keysvariable
   *
   * @return array
   */
  protected function assignPostDeployStages(array $arrayempty, string $keysvariable): array {
    $stages = [
      'launch_ode' => 'Deploy Drupal',
    ];
    if (!empty($arrayempty[$keysvariable])) {
      foreach ($stages as $jobs => $staging) {
        if (strstr($keysvariable, $jobs)) {
          $arrayempty[$keysvariable]['stage'] = $staging;
          $arrayempty[$keysvariable]['needs'] = ['Create artifact from branch'];
          continue;
        }
      }
      if (empty($arrayempty[$keysvariable]['stage'])) {
        $arrayempty[$keysvariable]['stage'] = 'Deploy Drupal';
        $arrayempty[$keysvariable]['needs'] = ['Create artifact from branch'];
      }
    }
    return $arrayempty;
  }

  /**
   * Merging the post-deploy job array.
   *
   * @param array $arrayempty
   */
  protected function mergePostDeployCode(array $arrayempty) {
    $build_dump = Yaml::dump($arrayempty);
    $build_parse = Yaml::parse($build_dump);
    $this->migrateScriptData = array_merge($this->migrateScriptData, $build_parse);
    $this->createGitLabCiFile();
  }

  /**
   * @return array
   */
  public function getMigrateScriptData(): array {
    return $this->migrateScriptData;
  }

  /**
   * @param array $migrateScriptData
   */
  public function setMigrateScriptData(array $migrateScriptData): void {
    $this->migrateScriptData = $migrateScriptData;
  }

}
