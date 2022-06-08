<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
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
    $this->authenticateWithGitLab();
    $this->writeApiTokenMessage($input);
    $cloud_key = $this->determineApiKey($input, $output);
    $cloud_secret = $this->determineApiSecret($input, $output);
    // We may already be authenticated with Acquia Cloud Platform via a refresh token.
    // But, we specifically need an API Token key-pair of Code Studio.
    // So we reauthenticate to be sure we're using the provided credentials.
    $this->reAuthenticate($cloud_key, $cloud_secret, $this->cloudCredentials->getBaseUri());
    $cloud_application_uuid = $this->determineCloudApplication();

    // Get Cloud account.
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $account_adapter = new Account($acquia_cloud_client);
    $account = $account_adapter->get();
    $this->validateRequiredCloudPermissions($acquia_cloud_client, $cloud_application_uuid, $account);
    $this->setGitLabProjectDescription($cloud_application_uuid);

    // Get Cloud application.
    $cloud_application = $this->getCloudApplication($cloud_application_uuid);
    $project = $this->determineGitLabProject($cloud_application);

    // Migrate acquia-pipeline file
    $this->checkGitLabCiCdVariables($project);
    $this->validateCwdIsValidDrupalProject();
    $acquia_pipelines_file_details = $this->getAcquiaPipelinesFileContents($project);
    $acquia_pipelines_file_contents = $acquia_pipelines_file_details['file_contents'];
    $acquia_pipelines_file_name = $acquia_pipelines_file_details['filename'];
    $gitlab_ci_file_contents = $this->getGitLabCiFileTemplate();
    $this->migrateVariablesSection($acquia_pipelines_file_contents, $gitlab_ci_file_contents);
    $this->migrateEventsSection($acquia_pipelines_file_contents, $gitlab_ci_file_contents);
    $this->removeEmptyScript($gitlab_ci_file_contents);
    $this->createGitLabCiFile($gitlab_ci_file_contents, $acquia_pipelines_file_name);
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
  protected function checkGitLabCiCdVariables(array $project) {
    $gitlab_cicd_variables = CodeStudioCiCdVariables::getList();
    $gitlab_cicd_existing_variables = $this->gitLabClient->projects()->variables($project['id']);
    $existing_keys = array_column($gitlab_cicd_existing_variables, 'key');
    foreach ($gitlab_cicd_variables as $gitlab_cicd_variable) {
      if (array_search($gitlab_cicd_variable, $existing_keys) === FALSE) {
        throw new AcquiaCliException("Code Studio CI/CD variable {$gitlab_cicd_variable} is not configured properly");
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
      return [
        'file_contents' => Yaml::parse($file_contents, Yaml::PARSE_OBJECT),
         'filename' =>  $filename
        ];
    }
    else {
      throw new AcquiaCliException("Missing 'acquia-pipelines.yml' file which is required to migrate the project to Code Studio.");
    }
  }

  /**
   * Migrating standard template to .gitlab-ci.yml file.
   *
   * @return array
   */
  protected function getGitLabCiFileTemplate(): array {
    return [
        'include' => ['project' => 'acquia/standard-template', 'file' => '/gitlab-ci/Auto-DevOps.acquia.gitlab-ci.yml'],
    ];
  }

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
   * @param array $acquia_pipelines_file_contents
   * @param string $event_name
   *
   * @return null
   */
  protected function getPipelinesSection(array $acquia_pipelines_file_contents, string $event_name) {
    if (!array_key_exists('events', $acquia_pipelines_file_contents)) {
      return NULL;
    }
    if (!array_key_exists($event_name, $acquia_pipelines_file_contents['events'])) {
      return NULL;
    }
    if (!array_key_exists('steps', $acquia_pipelines_file_contents['events'][$event_name])) {
      return NULL;
    }
    return $acquia_pipelines_file_contents['events'][$event_name]['steps'];
  }

  /**
   * @param array $acquia_pipelines_file_contents
   * @param array $gitlab_ci_file_contents
   */
  protected function migrateEventsSection(array $acquia_pipelines_file_contents, array &$gitlab_ci_file_contents) {
    $events_map = [
      'build' => [
        'skip' => [
          'composer install' => [
            'message' => 'Code Studio AutoDevOps will run `composer install` by default. Skipping migration of this command in your acquia-pipelines.yml file:',
            'prompt' => FALSE,
            ],
          '${BLT_DIR}' => [
            'message' => 'Code Studio AutoDevOps will run BLT commands for you by default. Do you want to skip migrating the following command?',
            'prompt' => TRUE,
          ],
        ],
        'default_stage' => 'Test Drupal',
        'stage' => [
          'setup' => 'Build Drupal',
          'npm run build' => 'Build Drupal',
          'validate' => 'Test Drupal',
          'tests' => 'Test Drupal',
          'test'  => 'Test Drupal',
          'npm test' => 'Test Drupal',
          'artifact' => 'Deploy Drupal',
          'deploy' => 'Deploy Drupal',
        ],
        'needs' => [
          'Build Code',
          'Manage Secrets'
        ],
      ],
      'post-deploy' => [
        'skip' => [
          'launch_ode' => [
            'message' => 'Code Studio AutoDevOps will run Launch a new Continuous Delivery Environment (CDE) automatically for new merge requests. Skipping migration of this command in your acquia-pipelines.yml file:',
            'prompt' => FALSE,
            ]
        ],
        'default_stage' => 'Deploy Drupal',
        'stage' => [
          'launch_ode' => 'Deploy Drupal',
        ],
        'needs' => [
          'Create artifact from branch'
        ]
      ]
    ];

    $code_studio_jobs = [];
    foreach ($events_map as $event_name => $event_map) {
      $event_steps = $this->getPipelinesSection($acquia_pipelines_file_contents, $event_name);
      if ($event_steps) {
        foreach ($event_steps as $step) {
          $script_name = array_keys($step)[0];
          if (!array_key_exists('script', $step[$script_name]) || empty($step[$script_name]['script'])) {
            continue;
          }
          if ($stage = $this->assignStageFromKeywords($event_map['stage'], $script_name)) {
            $code_studio_jobs[$script_name]['stage'] = $stage;
          }
          foreach ($step[$script_name]['script'] as $command) {
            foreach ($event_map['skip'] as $needle => $message_config) {
              if (str_contains($command, $needle)) {
                if ($message_config['prompt']) {
                  $answer = $this->io->confirm($message_config['message'] . PHP_EOL . $command, FALSE);
                  if ($answer == 1) {
                    $code_studio_jobs[$script_name]['script'][] = $command;
                    $code_studio_jobs[$script_name]['script']=array_values(array_unique($code_studio_jobs[$script_name]['script']));
                  }
                  else{
                    if (($key = array_search($command, $code_studio_jobs[$script_name]['script'])) !== FALSE) {
                      unset($code_studio_jobs[$script_name]['script'][$key]);
                    }
                  }
                }
                else {
                  $this->io->note([
                    $message_config['message'],
                    $command,
                  ]);
                }
                break;
              }
              else {
                if(array_key_exists($script_name, $code_studio_jobs) && array_key_exists('script', $code_studio_jobs[$script_name]) && in_array($command, $code_studio_jobs[$script_name]['script'])){
                  break;
                }
                if(!array_key_exists($script_name, $event_map['skip']) ){
                  $code_studio_jobs[$script_name]['script'][] = $command;
                  $code_studio_jobs[$script_name]['script']=array_values(array_unique($code_studio_jobs[$script_name]['script']));
                }
              }
            }
            if (array_key_exists($script_name, $code_studio_jobs) && !array_key_exists('stage', $code_studio_jobs[$script_name])
              && $stage = $this->assignStageFromKeywords($event_map['stage'], $command)) {
              $code_studio_jobs[$script_name]['stage'] = $stage;
            }
          }
          if (!array_key_exists('stage', $code_studio_jobs[$script_name])) {
            $code_studio_jobs[$script_name]['stage'] = $event_map['default_stage'];
          }
          $code_studio_jobs[$script_name]['needs'] = $event_map['needs'];
        }
        $gitlab_ci_file_contents = array_merge($gitlab_ci_file_contents, $code_studio_jobs);
        $this->io->success([
          "Completed migration of the $event_name step in your acquia-pipelines.yml file",
        ]);
      }
      else {
        $this->io->writeln([
          "acquia-pipeline.yml file does not contain $event_name step to migrate",
        ]);
      }
    }

  }

  /**
   *
   * Removing empty script.
   */
  protected function removeEmptyScript(array &$gitlab_ci_file_contents) {
    foreach($gitlab_ci_file_contents as $key => $value){
      if(array_key_exists('script', $value) && empty($value['script'])){
        unset($gitlab_ci_file_contents[$key]);
      }
    }
  }

  /**
   * Creating .gitlab-ci.yml file.
   */
  protected function createGitLabCiFile(array $contents,$acquia_pipelines_file_name) {
    $gitlab_ci_filepath = Path::join($this->repoRoot, '.gitlab-ci.yml');
    $this->localMachineHelper->getFilesystem()->dumpFile($gitlab_ci_filepath, Yaml::dump($contents, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    $this->localMachineHelper->getFilesystem()->remove($acquia_pipelines_file_name);
  }

  /**
   * @param array $keywords
   * @param string $haystack
   *
   * @return string|null
   */
  protected function assignStageFromKeywords(array $keywords, string $haystack): ?string {
    foreach ($keywords as $needle => $stage) {
      if (str_contains($haystack, $needle)) {
        return $stage;
      }
    }
    return NULL;
  }

}
