<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Response\ApplicationResponse;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\HttpClient\Builder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

/**
 * Class CodeStudioPipelinesMigrateCommand.
 */
class CodeStudioPipelinesMigrateCommand extends CommandBase {

  protected static $defaultName = 'codestudio:pipelines-migrate';

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
  private $gitlabToken;

  /**
   * @var string
   */
  private $gitlabHost;

  /**
   * @var string
   */
  private $acquia_pipeline_file_parse;

  /**
   * @var array
   */
  private $emptyArray;

  /**
   * @var array
   */
  private $avlChoice = ['yes', 'no'];

  /**
   * @var string
   */
  private $filename;

  /**
   * @var string
   */
  private $defaultChoice = 'no';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Migrate acquia-pipeline file to gitlab file for a given Acquia Cloud application')
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
    $this->emptyArray=[];
    $this->gitlabHost = $this->getGitLabHost();
    $this->env = $this->validateEnvironment();
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

    $cloud_key = $this->determineApiKey($input, $output);
    $cloud_secret = $this->determineApiSecret($input, $output);
    // We may already be authenticated with Acquia Cloud Platform via a refresh token.
    // But, we specifically need an API Token key-pair of Code Studio.
    // So we reauthenticate to be sure we're using the provided credentials.
    $this->reAuthenticate($cloud_key, $cloud_secret, $this->cloudCredentials->getBaseUri());

    $this->checklist = new Checklist($output);
    $this->appUuid = $this->determineCloudApplication();
    // Get Cloud application.
    $cloud_application = $this->getCloudApplication($this->appUuid);
    $this->gitLabProjectDescription = "Source repository for Acquia Cloud Platform application <comment>$this->appUuid</comment>";
    $project = $this->determineGitLabProject($cloud_application);

    // Migrate acquia-pipeline file
    $this->getGitLabCiCdVariables($project, $this->appUuid, $cloud_key, $cloud_secret);
    $this->checkPipelineExists($project);
    $this->migrateCSStandardTemplate();
    $this->migrateVariables();
    $this->migrateBuild();
    $this->migratePostDeploy();
    $this->io->success([
      "",
      "*********** Migration completed successfully. *********\n",
      "Created .gitlab-ci.yml and removed acquia-pipeline.yml file.\n",
      "In order to run Pipeline, push .gitlab-ci.yaml to Main branch of Code Studio project.\n",
      "Check your pipeline is running in Code Studio for your project.\n"
    ]);

    // Get Cloud account.
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $account_adapter = new Account($acquia_cloud_client);
    $account = $account_adapter->get();
    $this->validateRequiredCloudPermissions($acquia_cloud_client, $this->appUuid, $account);
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validateEnvironment() {
    if (!getenv('GITLAB_HOST')) {
      throw new AcquiaCliException('The GITLAB_HOST environmental variable must be set.');
    }
  }

  /**
   * Search for existing project in code studio.
   * @param ApplicationResponse $cloud_application
   *
   * @return array
   */
  protected function determineGitLabProject(ApplicationResponse $cloud_application) {
    $projects = $this->gitLabClient->projects()->all(['search' => $cloud_application->uuid]);
    if (count($projects) == 1) {
      return reset($projects);
    }
    else {
      throw new AcquiaCliException("[ERROR] '{$cloud_application->name}' is not configured as Code Studio project.\n [Help] You can configure the '{$cloud_application->name}' project in Code Studio by using 'acli cs:wizard' command \n You can find Code Studio documentation at https://docs.acquia.com/code-studio/getting-started/#create-code-studio-project \n");
    }
  }

  /**
   * Check wether wizard command is executed by checking the env variable of codestudio project.
   * @param array $project
   * @param string $cloud_application_uuid
   * @param string $cloud_key
   * @param string $cloud_secret
   * @param string $project_access_token_name
   * @param string $project_access_token
   */
  protected function getGitLabCiCdVariables(array $project, string $cloud_application_uuid, string $cloud_key, string $cloud_secret): array {
    //todo check all 5 variables
    $GLAB_TOKEN_NAME = "ACQUIA_GLAB_TOKEN_SECRET";
    $gitlab_cicd_existing_variables = $this->gitLabClient->projects()->variables($project['id']);
    foreach ($gitlab_cicd_existing_variables as $variable) {
      if ($variable['key'] == $GLAB_TOKEN_NAME) {
        return $variable;
      }
    }
    foreach ($gitlab_cicd_existing_variables as $variable) {
      if ($variable['key'] != $GLAB_TOKEN_NAME) {
        throw new AcquiaCliException("[Error] Code Studio CI/CD environment 'Variable_Name' variable is not configured properly. \n[Help] To set all the CI/CD environment variables, configure the project in Code Studio by using 'acli cs:wizard' command \n You can find Code Studio documentation at https://docs.acquia.com/code-studio/getting-started/#create-code-studio-project \n OR \n Set the above required CI/CD environment variables in Code Studio project.\n");
      }
    }
  }

  //todo : check the current directry == projects;

  /**
   * Check acquia-pipeline.yml file exists in the root repo and remove ci_config_path from codestudio project.
   * @param array $project
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function checkPipelineExists(array $project) {
    $pipelines_filepath_yml = Path::join($this->repoRoot, 'acquia-pipelines.yml');
    $pipelines_filepath_yaml = Path::join($this->repoRoot, 'acquia-pipelines.yaml');
    if ($this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yml) or $this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yaml)) {
      // print("file exists");
      $this->gitLabClient->projects()
        ->update($project['id'], ['ci_config_path' => '']);
      // print_r("\nRemoved ci_config_path \n");
      foreach (glob("acquia-pipelines.*") as $this->filename) {
        echo "File name is : $this->filename\n";
      }
      $file_contents = file_get_contents($this->filename);
      $this->acquia_pipeline_file_parse = Yaml::parse($file_contents, Yaml::PARSE_OBJECT);
    }
    else {
      throw new AcquiaCliException("[Error] Missing 'acquia-pipelines.yaml' file which is required to migrate the project to Code Studio.");
    }
  }

  /**
   * Migrating standard template to .gitlab-ci.yml file.
   */
  protected function migrateCSStandardTemplate() {
    $auto_devops_pipeline = [
        'include' => ['project' => 'acquia/standard-template', 'file' => '/gitlab-ci/Auto-DevOps.acquia.gitlab-ci.yml'],
      ];
    $auto_devops_dump_file = Yaml::dump($auto_devops_pipeline);
    $auto_devops_parse_file = Yaml::parse($auto_devops_dump_file);
    $this->emptyArray = array_merge($this->emptyArray, $auto_devops_parse_file);
    $this->createGitlabCiFile($this->emptyArray);
  }

  //todo : catch execpetion if any and throw error.

  /**
   * Migrating varibales to .gitlab-ci.yml file.
   */
  protected function migrateVariables() {
    $varData = isset($this->acquia_pipeline_file_parse['variables'])?$this->acquia_pipeline_file_parse['variables']:FALSE;
    if ($varData){
      $variables_dump = Yaml::dump(['variables' => $varData]);
      $remove_global = preg_replace('/global:/', '', $variables_dump);
      $variables_parse = Yaml::parse($remove_global);
      $this->emptyArray = array_merge($this->emptyArray, $variables_parse);
      $this->createGitlabCiFile($this->emptyArray);
      $this->io->writeln([
        "",
        "\n*********** Migration completed for variables section. *********\n",
      ]);
    }
    else {
      $this->io->writeln([
        "",
        "\n*********** acquia-pipeline file does not contain variables to migrate. *********\n",
      ]);
    }
  }

  /**
   * Migrating build job to .gitlab-ci.yml file.
   */
  protected function migrateBuild() {
    $buildJob=isset($this->acquia_pipeline_file_parse['events']['build']['steps'])?$this->acquia_pipeline_file_parse['events']['build']['steps']:[];
    if (!empty($buildJob)){
      $response_composer = NULL;
      $response_BLT = NULL;

      $chunck = array_chunk($buildJob, 1);
      foreach ($chunck as $key => $value){
        $keysvar = array_keys($value[0])[0];
        if(empty($value[0][$keysvar]['script'])){
          continue;
        }
        foreach ($value[0][$keysvar]['script'] as $script){
          if(strstr ($script, 'composer')) {
            if(empty($response_composer)){
              $ques = "Composer script is part of Code Studio Auto DevOps Pipeline, do you still want to migrate Composer script?(yes,no)";
              $response_composer = $this->getCustResponse($ques, $this->avlChoice, $this->defaultChoice);
            }
            if($response_composer == 'yes'){
              $arrayempty[$keysvar]['script'][] = $script;
              continue;
            }
            // print_r("Using job from code studio\n\n");
          }
          else if(strstr ($script, '${BLT_DIR}')) {
            if(empty($response_BLT)){
              $ques = "BLT script is part of Code Studio Auto DevOps Pipeline, do you still want to migrate BLT script?(yes,no)";
              $response_BLT = $this->getCustResponse($ques, $this->avlChoice, $this->defaultChoice);
            }
            if($response_BLT == 'yes'){
              $arrayempty[$keysvar]['script'][] = $script;
              continue;
            }
            // print_r("Using job from code studio\n\n");
          }
          else{
            $arrayempty[$keysvar]['script'][] = $script;
          }
        }
        $arrayempty=$this->assignBuildStages($arrayempty, $keysvar);

      }
      $this->io->writeln([
        "",
        "\n*********** Migration completed for Build job section. *********\n",
      ]);
      $this->mergeBuildCode($arrayempty);
    }
    else{
      $this->io->writeln([
        "",
        "\n*********** acquia-pipeline file does not contain Build job to migrate. *********\n",
      ]);
    }
  }

  /**
   * Getting customer response.
   * @param string $question
   * @param array $avlChoice
   * @param string $defaultChoice
   */
  protected function getCustResponse($question,$avlChoice,$defaultChoice) {
    $ques = new Question($question, 'no');
    $ques->setAutocompleterValues($avlChoice);
    $defaultChoice = $this->io->askQuestion($ques);
    return $defaultChoice;
  }

  /**
   * Assign build job its equivalent stages.
   * @param array $arrayempty
   * @param string $keysvar
   */
  protected function assignBuildStages($arrayempty,$keysvar) {
    $stages=[
      'setup' => 'Build Drupal',
      'npm run build' => 'Build Drupal',
      'validate' => 'Test Drupal',
      'tests' => 'Test Drupal',
      'npm test' => 'Test Drupal',
      'artifact' => 'Deploy Drupal',
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
    $this->emptyArray = array_merge($this->emptyArray, $build_parse);
    $this->createGitlabCiFile($this->emptyArray);
  }

  /**
   * Creating .gitla-ci.yml file.
   */
  protected function createGitlabCiFile() {
    $gitlab_ci_filepath = Path::join($this->repoRoot, '.gitlab-ci.yml');
    $this->localMachineHelper->getFilesystem()->dumpFile($gitlab_ci_filepath, Yaml::dump($this->emptyArray, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    $this->localMachineHelper->getFilesystem()->remove($this->filename);
  }

  /**
   * Migrating post build job to .gitlab-ci.yml file.
   */
  protected function migratePostDeploy() {
    $postDeployJob=isset($this->acquia_pipeline_file_parse['events']['post-deploy']['steps'])?$this->acquia_pipeline_file_parse['events']['post-deploy']['steps']:[];
    if(!empty($postDeployJob)){
      $reply = NULL;

      $chunckbuild = array_chunk($postDeployJob, 1);
      foreach ($chunckbuild as $keyss => $valuess){
        $keysvariable = array_keys($valuess[0])[0];
        if(empty($valuess[0][$keysvariable]['script'])){
          continue;
        }
        foreach ($valuess[0][$keysvariable]['script'] as $scripting){
          if(strstr ($scripting, 'launch_ode')){
            if(empty($reply)){
              $ques = "launch_ode script is part of Code Studio Auto DevOps Pipeline, do you still want to migrate launch_ode script?(yes,no)";
              $reply = $this->getCustResponse($ques, $this->avlChoice, $this->defaultChoice);
            }
            if($reply == 'yes'){
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
        "\n*********** Migration completed for Post-Deploy job section. *********\n",
      ]);
    }
    else{
      $this->io->writeln([
        "",
        "\n*********** acquia-pipeline file does not contain Post-Deploy job to migrate. *********\n",
      ]);
    }
  }

  /**
   * Assign post-build job its equivalent stages.
   * @param array $arrayempty
   * @param string $keysvariable
   */
  protected function assignPostDeployStages($arrayempty,$keysvariable) {
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
   * @param array $arrayempty
   */
  protected function mergePostDeployCode($arrayempty) {
    $build_dump = Yaml::dump($arrayempty);
    $build_parse = Yaml::parse($build_dump);
    $this->emptyArray = array_merge($this->emptyArray, $build_parse);
    $this->createGitlabCiFile($this->emptyArray);
  }

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

}
