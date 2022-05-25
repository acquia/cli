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
   * @var string
   */
  private $acquiaPipelineFileParse;

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
    $this->checkCurrectDirectory();
    $this->checkPipelinesFileExists($project);
    $this->migrateToCodeStudioStandardTemplate();
    $this->migrateVariablesSection();
    $this->migrateBuildSection();
    $this->migratePostDeploySection();
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
   * Check wether wizard command is executed by checking the env variable of codestudio project.
   * @param array $project
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getGitLabCiCdVariables(array $project) {
    $code_studio_cicd_variables = ['ACQUIA_APPLICATION_UUID','ACQUIA_CLOUD_API_TOKEN_KEY','ACQUIA_CLOUD_API_TOKEN_SECRET','ACQUIA_GLAB_TOKEN_NAME','ACQUIA_GLAB_TOKEN_SECRET'];
    $gitlab_cicd_existing_variables = $this->gitLabClient->projects()->variables($project['id']);
    $diff_keys_count = count(array_intersect($code_studio_cicd_variables, array_column($gitlab_cicd_existing_variables, 'key')));
    if(!(count($code_studio_cicd_variables) == $diff_keys_count)){
      throw new AcquiaCliException("[Error] Code Studio CI/CD environment 'Variable_Name' variable is not configured properly. \n[Help] To set all the CI/CD environment variables, configure the project in Code Studio by using 'acli cs:wizard' command \n You can find Code Studio documentation at https://docs.acquia.com/code-studio/getting-started/#create-code-studio-project \n OR \n Set the above required CI/CD environment variables in Code Studio project.\n");
    }
  }

  /**
   * Check the current directory is thr project which required to migrate.
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function checkCurrectDirectory() {
    $currentDirectoryName = basename(getcwd());
    $projectName = $this->projects[0]['name'];
    if ($currentDirectoryName != $projectName) {
      throw new AcquiaCliException("Your current working directory does not appear to be Drupal repository");
    }
  }

  /**
   * Check acquia-pipeline.yml file exists in the root repo and remove ci_config_path from codestudio project.
   * @param array $project
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function checkPipelinesFileExists(array $project) {
    $pipelines_filepath_yml = Path::join($this->repoRoot, 'acquia-pipelines.yml');
    $pipelines_filepath_yaml = Path::join($this->repoRoot, 'acquia-pipelines.yaml');
    if ($this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yml) or $this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yaml)) {
      $this->gitLabClient->projects()
        ->update($project['id'], ['ci_config_path' => '']);
      $filename = implode('', glob("acquia-pipelines.*"));
      $file_contents = file_get_contents($filename);
      $this->acquiaPipelineFileParse = Yaml::parse($file_contents, Yaml::PARSE_OBJECT);
    }
    else {
      throw new AcquiaCliException("Missing 'acquia-pipelines.yaml' file which is required to migrate the project to Code Studio.");
    }
  }

  /**
   * Migrating standard template to .gitlab-ci.yml file.
   */
  protected function migrateToCodeStudioStandardTemplate() {
    $auto_devops_pipeline = [
        'include' => ['project' => 'acquia/standard-template', 'file' => '/gitlab-ci/Auto-DevOps.acquia.gitlab-ci.yml'],
      ];
    $auto_devops_dump_file = Yaml::dump($auto_devops_pipeline);
    $auto_devops_parse_file = Yaml::parse($auto_devops_dump_file);
    $this->migrateScriptData = array_merge($this->migrateScriptData, $auto_devops_parse_file);
    $this->createGitLabCiFile($this->migrateScriptData);
  }

  //todo : catch execpetion if any and throw error.

  /**
   * Migrating varibales to .gitlab-ci.yml file.
   */
  protected function migrateVariablesSection() {
    $varData = $this->acquiaPipelineFileParse['variables'] ?? FALSE;
    if ($varData){
      $variables_dump = Yaml::dump(['variables' => $varData]);
      $remove_global = preg_replace('/global:/', '', $variables_dump);
      $variables_parse = Yaml::parse($remove_global);
      $this->migrateScriptData = array_merge($this->migrateScriptData, $variables_parse);
      $this->createGitLabCiFile($this->migrateScriptData);
      $this->io->writeln([
        "",
        "***********Migration completed for variables section.*********",
      ]);
    }
    else {
      $this->io->writeln([
        "",
        "***********acquia-pipeline file does not contain variables to migrate.*********",
      ]);
    }
  }

  /**
   * Migrating build job to .gitlab-ci.yml file.
   */
  protected function migrateBuildSection() {
    $buildJob= $this->acquiaPipelineFileParse['events']['build']['steps'] ?? [];
    $arrayempty = [];
    if (!empty($buildJob)){
      $response_composer = NULL;
      $response_BLT = NULL;

      foreach ($buildJob as $key => $value){
        $keysvar = array_keys($value)[0];

        if((!array_key_exists('script', $value[$keysvar])) || empty($value[$keysvar]['script'])){
          continue;
        }
        foreach ($value[$keysvar]['script'] as $script){
          if(strstr ($script, 'composer')) {
            if(empty($response_composer)){
              $ques = "Composer script is part of Code Studio Auto DevOps Pipeline, do you still want to migrate Composer script?";
              $response_composer = ($this->io->confirm($ques, FALSE))?'yes':'no';

            }
            if($response_composer == 'yes'){
              $arrayempty[$keysvar]['script'][] = $script;
              continue;
            }
          }
          else if(strstr ($script, '${BLT_DIR}')) {
            if(empty($response_BLT)){
              $ques = "BLT script is part of Code Studio Auto DevOps Pipeline, do you still want to migrate BLT script?";
              $response_BLT = ($this->io->confirm($ques, FALSE))?'yes':'no';
            }
            if($response_BLT == 'yes'){
              $arrayempty[$keysvar]['script'][] = $script;
              continue;
            }
          }
          else{
            $arrayempty[$keysvar]['script'][] = $script;
          }
        }
        $arrayempty=$this->assignBuildStages($arrayempty, $keysvar);
      }
      $this->io->writeln([
        "",
        "***********Migration completed for Build job section.*********",
      ]);
      $this->mergeBuildCode($arrayempty);
    }
    else{
      $this->io->writeln([
        "",
        "***********acquia-pipeline file does not contain Build job to migrate.*********",
      ]);
    }
  }

  /**
   * Assign build job its equivalent stages.
   * @param array $arrayempty
   * @param string $keysvar
   */
  protected function assignBuildStages($arrayempty,$keysvar): array {
    $stages=[
      'setup' => 'Build Drupal',
      'npm run build' => 'Build Drupal',
      'validate' => 'Test Drupal',
      'tests' => 'Test Drupal',
      'test'  => 'Test Drupal',
      'npm test' => 'Test Drupal',
      'artifact' => 'Deploy Drupal',
      'deploy' => 'Deploy Drupal',
    ];
    if(!empty($arrayempty[$keysvar])){
      foreach($stages as $job => $stage){
        if(strstr ($keysvar, $job) ){
          $arrayempty[$keysvar]['stage'] = $stage;
          continue;
        }
      }
      if(empty($arrayempty[$keysvar]['stage'])){
        $arrayempty[$keysvar]['stage'] = 'Build Drupal';
      }
    }
    return $arrayempty;
  }

  /**
   * Merging the build job array.
   * @param array $arrayempty
   */
  protected function mergeBuildCode($arrayempty) {
    $build_dump = Yaml::dump($arrayempty);
    $build_parse = Yaml::parse($build_dump);
    $this->migrateScriptData = array_merge($this->migrateScriptData, $build_parse);
    $this->createGitLabCiFile($this->migrateScriptData);
  }

  /**
   * Creating .gitla-ci.yml file.
   */
  protected function createGitLabCiFile() {
    $gitlab_ci_filepath = Path::join($this->repoRoot, '.gitlab-ci.yml');
    $this->localMachineHelper->getFilesystem()->dumpFile($gitlab_ci_filepath, Yaml::dump($this->migrateScriptData, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    $this->localMachineHelper->getFilesystem()->remove($this->filename);
  }

  /**
   * Migrating post build job to .gitlab-ci.yml file.
   */
  protected function migratePostDeploySection() {
    $postDeployJob= $this->acquiaPipelineFileParse['events']['post-deploy']['steps'] ?? [];
    if(!empty($postDeployJob)){
      $response_launch_ode = NULL;
      foreach ($postDeployJob as $keyss => $valuess){
        $keysvariable = array_keys($valuess)[0];
        if(empty($valuess[$keysvariable]['script'])){
          continue;
        }
        foreach ($valuess[$keysvariable]['script'] as $scripting){
          if(strstr ($scripting, 'launch_ode')){
            if(empty($response_launch_ode)){
              $ques = "launch_ode script is part of Code Studio Auto DevOps Pipeline, do you still want to migrate launch_ode script?";
              $response_launch_ode = ($this->io->confirm($ques, FALSE))?'yes':'no';
            }
            if($response_launch_ode == 'yes'){
              $arrayempty[$keysvariable]['script'][] = $scripting;
              continue;
            }
            unset($arrayempty[$keysvariable]);
            break;
          }
          else{
            $arrayempty[$keysvariable]['script'][] = $scripting;
          }
        }
        $arrayempty=$this->assignPostDeployStages($arrayempty, $keysvariable);
      }
      $this->mergePostDeployCode($arrayempty);
      $this->io->writeln([
        "",
        "***********Migration completed for Post-Deploy job section.*********",
      ]);
    }
    else{
      $this->io->writeln([
        "",
        "***********acquia-pipeline file does not contain Post-Deploy job to migrate.*********",
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
    $stagess=[
      'launch_ode' => 'Deploy Drupal',
    ];
    if(!empty($arrayempty[$keysvariable])){
      foreach($stagess as $jobs => $stageing){
        if(strstr ($keysvariable, $jobs) ){
          $arrayempty[$keysvariable]['stage'] = $stageing;
          $arrayempty[$keysvariable]['needs'] = ['Create artifact from branch'];
          continue;
        }
      }
      if(empty($arrayempty[$keysvariable]['stage'])){
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
    $this->createGitLabCiFile($this->migrateScriptData);
  }

  /**
   * @return array
   */
  public function getMigrateScriptData() {
    return $this->migrateScriptData;
  }

  /**
   * @param array $migrateScriptData
   */
  public function setMigrateScriptData($migrateScriptData): void {
    $this->migrateScriptData = $migrateScriptData;
  }

}
