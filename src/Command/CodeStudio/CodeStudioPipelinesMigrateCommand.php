<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Question\Question;

/**
 * Class CodeStudioPipelinesMigrateCommand.
 */
class CodeStudioPipelinesMigrateCommand extends CommandBase {

  protected static $defaultName = 'codestudio:pipelines-migrate';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('@todo')
      ->setAliases(['cs:pipelines']);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    // if (!$this->repoRoot) {
    //   $this->io->error(
    //     ['You current working directory does not appear to be a Drupal repository!'],
    //   );
    //   return 1;
    // }
// build array and test array empty and extra job array 
    $pipelines_filepath_yml = Path::join($this->repoRoot, 'acquia-pipelines.yml');
    $pipelines_filepath_yaml = Path::join($this->repoRoot, 'acquia-pipelines.yaml');


    if ($this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yml) or $this->localMachineHelper->getFilesystem()->exists($pipelines_filepath_yaml)) {
      print_r("\nCurrent directory consists of Acquia pipeline file.\n");

      foreach (glob("acquia-pipelines.*") as $filename) {
        echo "File name is : $filename\n";
      }
  
      $file_contents = file_get_contents($filename);
      $acquia_pipeline_file_parse = Yaml::parse($file_contents, Yaml::PARSE_OBJECT);
    

      $emptyArray = [];
      #auto_devops code
      $auto_devops_pipeline = [
        'include' => ['project' => 'acquia/standard-template', 'file' => '/gitlab-ci/Auto-DevOps.acquia.gitlab-ci.yml'],
      ];
      $auto_devops_dump_file = Yaml::dump($auto_devops_pipeline);
      $auto_devops_parse_file = Yaml::parse($auto_devops_dump_file);

      $emptyArray = array_merge($emptyArray,$auto_devops_parse_file);

      error_reporting(E_ALL ^ E_NOTICE);  

      #migrate variables
      if ($varData = $acquia_pipeline_file_parse['variables']){
        $variables_dump = Yaml::dump(['variables' => $varData]);
        $remove_global = preg_replace('/global:/', '', $variables_dump);
        $variables_parse = Yaml::parse($remove_global);
        $emptyArray = array_merge($emptyArray,$variables_parse); 
      }
      else {
        print_r("\nno varaibles defined\n\n");
      }
      $arrayempty = array();

      #migrate build
      if ($varDataBuild = $acquia_pipeline_file_parse['events']['build']['steps']){
          $response_build = null;
          $response_npm = null;
          $response_BLT = null;


          $defaultarray = array("npm run build","composer");
          $chunck = array_chunk($varDataBuild, 1);
          foreach ($chunck as $key => $value){
            $keysvar = array_keys($value[0])[0];
            if(empty($value[0][$keysvar]['script'])){
              continue;
            } 
            foreach ($value[0][$keysvar]['script'] as $script){ 
                if((strstr ($script, 'composer') && empty($response_build))) {
                  $choice = 'no';
                  $question = new Question("Code Studio is already taking care of 'Composer' script, do you still want to migrate it(yes,no)? ", 'no');
                  $question->setAutocompleterValues(['yes', 'no']);
                  $choice = $this->io->askQuestion($question);
                  $response_build = $choice;
                }
                else if((strstr ($script, '${BLT_DIR}') && empty($response_BLT))) {
                  $choice = 'no';
                  $question = new Question("Code Studio is already taking care of 'BLT' script, do you still want to migrate it(yes,no)? ", 'no');
                  $question->setAutocompleterValues(['yes', 'no']);
                  $choice = $this->io->askQuestion($question);
                  $response_BLT = $choice;
                }

                if (strstr ($script, 'composer')) {
                  if($choice == 'yes'){
                    $arrayempty[$keysvar]['script'][] = $script;
                    continue;
                  }
                  print_r("Using build drupal job from code studio\n\n");
                  unset($arrayempty[$keysvar]);
                  
                }
                else if (strstr ($script, '${BLT_DIR}')) {
                  if($choice == 'yes'){
                    $arrayempty[$keysvar]['script'][] = $script;
                    continue;
                  }
                  print_r("Using build drupal job from code studio\n\n");
                  unset($arrayempty[$keysvar]);
                }
                else{
                  $arrayempty[$keysvar]['script'][] = $script;
                }
            }
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
          }
          $build_dump = Yaml::dump($arrayempty);
          $build_parse = Yaml::parse($build_dump);
          $emptyArray = array_merge($emptyArray,$build_parse);     
      }
      else{
        print_r("\nNo build job is defined\n\n");
      } 

      if($varDataPostBuild = $acquia_pipeline_file_parse['events']['post-deploy']['steps']){
          $chunckbuild = array_chunk($varDataPostBuild, 1);
          $reply = null;
          foreach ($chunckbuild as $keyss => $valuess){
            $keysvariable = array_keys($valuess[0])[0];
            if(empty($valuess[0][$keysvariable]['script'])){
              continue;
            }
            foreach ($valuess[0][$keysvariable]['script'] as $scripting){ 
              if(strstr ($scripting, 'launch_ode') && empty($reply) ){
                $choice = 'no';
                $question = new Question("Code Studio is already taking care of '$scripting' script, do you still want to migrate it(yes,no)? ", 'no');
                $question->setAutocompleterValues(['yes', 'no']);
                $choice = $this->io->askQuestion($question);
                $reply = $choice;
              }
              if (strstr ($scripting, 'launch_ode') && $reply == 'yes') {
                $arrayempty[$keysvariable]['script'][] = $scripting;
                // echo "Key values:",$keysvariable;
                continue;
              }
              else if(strstr ($scripting, 'launch_ode') && $reply == 'no') {
                print_r("Using code studio inbuilt job\n\n");
                unset($arrayempty[$keysvariable]);
                break;
              }
              else{
                $arrayempty[$keysvariable]['script'][] = $scripting;
              }
            }

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
          }
          $build_dump = Yaml::dump($arrayempty);
          $build_parse = Yaml::parse($build_dump);
          $emptyArray = array_merge($emptyArray,$build_parse);    
      

      }  
      else{
          print_r("\nNo Post build job is defined\n\n");
      }
      
      $gitlab_ci_filepath = Path::join($this->repoRoot, '.gitlab-ci.yml');
      $this->localMachineHelper->getFilesystem()->dumpFile($gitlab_ci_filepath, Yaml::dump($emptyArray, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
      $this->io->success([
        "Created gitlab.yml",
      ]);
    }
    else {
      $this->io->error(
        ['Could not find .acquia-pipelines.yml file in ' . $this->repoRoot],
      );
    }

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
}
