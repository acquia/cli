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

  private $acquia_pipeline_file_parse;

  private $emptyArray;

  private $avlChoice = ['yes', 'no'];
  private $defaultChoice = 'no';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('@todo')
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
    // $this->gitlabHost = $this->getGitLabHost();

    // $this->io->info('Hello World');
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

    $this->getGitLabCiCdVariables($project, $this->appUuid, $cloud_key, $cloud_secret);

    $this->checkPipelineExists($project);
    $this->migrateCSStandardTemplate();
    $this->migrateVariables();
    $this->migrateBuild();

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
   * @param ApplicationResponse $cloud_application
   *
   * @return array
   */
  protected function determineGitLabProject(ApplicationResponse $cloud_application) {
    // Search for existing project in code studio.
    $projects = $this->gitLabClient->projects()->all(['search' => $cloud_application->uuid]);
    #print_r($projects);
    if (count($projects) == 1) {
      // print("Project exists");
      return reset($projects);
    }
    else {
      throw new AcquiaCliException( "Could not find any existing {$cloud_application->name} Code Studio project. Please run 'wizard' command to create the project in Code Studio.");
    }
  }

  /**
   * Getting cicd variables.
   * @param array $project
   * @param string $cloud_application_uuid
   * @param string $cloud_key
   * @param string $cloud_secret
   * @param string $project_access_token_name
   * @param string $project_access_token
   */
  protected function getGitLabCiCdVariables(array $project, string $cloud_application_uuid, string $cloud_key, string $cloud_secret): array {
    //Check wether wizard command is executed by checking the env variable.
    $GLAB_TOKEN_NAME = "ACQUIA_GLAB_TOKEN_SECRET";
    $gitlab_cicd_existing_variables = $this->gitLabClient->projects()->variables($project['id']);
    foreach ($gitlab_cicd_existing_variables as $variable) {
      if ($variable['key'] == $GLAB_TOKEN_NAME) {
        return $variable;
      }
    }
    foreach ($gitlab_cicd_existing_variables as $variable) {
      if ($variable['key'] != $GLAB_TOKEN_NAME) {
        throw new AcquiaCliException("Could not find 'ACQUIA_GLAB_TOKEN_SECRET' CICD variable in the project. Please make sure the project is created using wizard command.");
      }
    }
  }

  /**
   * Checking the acquia-pipeline file exists.
   * @param array $project
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function checkPipelineExists(array $project) {
    //Check acquia-pipeline.yml file exists and remove ci_config_path from codestudio project.
    $pipelines_filepath_yml = Path::join($this->repoRoot, 'acquia-pipelines.yml');
    $pipelines_filepath_yaml = Path::join($this->repoRoot, 'acquia-pipelines.yaml');
    if ($this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yml) or $this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yaml)) {
      print("file exists");
      $this->gitLabClient->projects()
        ->update($project['id'], ['ci_config_path' => '']);
      print_r("\nRemoved ci_config_path \n");

      foreach (glob("acquia-pipelines.*") as $filename) {
        echo "File name is : $filename\n";
      }
      $file_contents = file_get_contents($filename);
      $this->acquia_pipeline_file_parse = Yaml::parse($file_contents, Yaml::PARSE_OBJECT);
    }
    else {
      throw new AcquiaCliException("Could not find acquia-pipelines.yml file in the root directory of the Drupal project. Please add/move the acquia-pipelines.yml file in the root directory of the project");
    }
  }

  /**
   * Migrating standard template.
   */
  protected function migrateCSStandardTemplate() {
    // Autodveops code to migrate
    $auto_devops_pipeline = [
        'include' => ['project' => 'acquia/standard-template', 'file' => '/gitlab-ci/Auto-DevOps.acquia.gitlab-ci.yml'],
      ];
    $auto_devops_dump_file = Yaml::dump($auto_devops_pipeline);
    $auto_devops_parse_file = Yaml::parse($auto_devops_dump_file);
    $this->emptyArray = array_merge($this->emptyArray, $auto_devops_parse_file);
    $this->createGitlabCiFile($this->emptyArray);
  }

  /**
   * Migrating varibales.
   */
  protected function migrateVariables() {
    $varData = isset($this->acquia_pipeline_file_parse['variables'])?$this->acquia_pipeline_file_parse['variables']:FALSE;
    if ($varData){
      $variables_dump = Yaml::dump(['variables' => $varData]);
      $remove_global = preg_replace('/global:/', '', $variables_dump);
      $variables_parse = Yaml::parse($remove_global);
      $this->emptyArray = array_merge($this->emptyArray, $variables_parse);
      $this->createGitlabCiFile($this->emptyArray);
    }
    else {
      print_r("\nno varaibles defined\n\n");
    }
  }

  /**
   * Migrating build.
   */
  protected function migrateBuild() {
    $varDataBuild=isset($this->acquia_pipeline_file_parse['events']['build']['steps'])?$this->acquia_pipeline_file_parse['events']['build']['steps']:FALSE;
    if ($varDataBuild){
      $response_composer = NULL;
      $response_BLT = NULL;

      $chunck = array_chunk($varDataBuild, 1);
      foreach ($chunck as $key => $value){
        $keysvar = array_keys($value[0])[0];
        if(empty($value[0][$keysvar]['script'])){
          continue;
        }
        foreach ($value[0][$keysvar]['script'] as $script){
          if(strstr ($script, 'composer')) {
            if(empty($response_composer)){
              $ques = "Code Studio is already taking care of 'Composer' script, do you still want to migrate it(yes,no)?";
              $response_composer = $this->getCustResponse($ques, $this->avlChoice, $this->defaultChoice);
            }
            if($response_composer == 'yes'){
              $arrayempty[$keysvar]['script'][] = $script;
              continue;
            }
            print_r("Using job from code studio\n\n");
          }
          else if(strstr ($script, '${BLT_DIR}')) {
            if(empty($response_BLT)){
              $ques = "Code Studio is already taking care of 'BLT' script, do you still want to migrate it(yes,no)?";
              $response_BLT = $this->getCustResponse($ques, $this->avlChoice, $this->defaultChoice);
            }
            if($response_BLT == 'yes'){
              $arrayempty[$keysvar]['script'][] = $script;
              continue;
            }
            print_r("Using job from code studio\n\n");
          }
          else{
            $arrayempty[$keysvar]['script'][] = $script;
          }
        }
        $arrayempty=$this->assignStages($arrayempty, $keysvar);
      }
      $this->dumpBuildCode($arrayempty);
    }
    else{
      print_r("\nNo build job is defined\n\n");
    }
  }

  /**
   * Getting customer reponse.
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
   * Assign stages.
   * @param array $arrayempty
   * @param string $keysvar
   */
  protected function assignStages($arrayempty,$keysvar) {
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
   * Dumping code
   * @param array $arrayempty
   */
  protected function dumpBuildCode($arrayempty) {
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
    $this->io->success([
      "Created gitlab.yml",
    ]);
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
