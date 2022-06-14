<?php


namespace Acquia\Cli\Command\DrupalUpdate;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

class PackageUpdateScript
{

  /**
   * Drupal docroot directory folder path.
   * @var string
   */
  private string $drupalDocrootDirPath;

  /**
   * @return string
   */
  public function getDrupalDocrootDirPath(): string {
    return $this->drupalDocrootDirPath;
  }

  /**
   * SymfonyStyle io
   * @var SymfonyStyle
   */
  private SymfonyStyle $io;

  /**
   * @param string $drupal_root_dir_path
   */
  public function setDrupalDocrootDirPath(string $drupal_root_dir_path): void {
    $this->drupalDocrootDirPath = $drupal_root_dir_path . "/docroot";
  }

  /**
   * @var CheckPackageInfo
   */
  public CheckPackageInfo $checkPackageInfo;

  /**
   * @var UpdateScriptUtility
   */
  private UpdateScriptUtility $updateScriptUtility;

  /**
   * UpdateScript constructor.
   * @param InputInterface $input
   * @param OutputInterface $output
   * @param CheckPackageInfo $checkPackageInfo
   */
  public function __construct(InputInterface $input,
                              OutputInterface $output,
  CheckPackageInfo $checkPackageInfo ) {
    $this->checkPackageInfo = $checkPackageInfo;
    $this->updateScriptUtility = $this->checkPackageInfo->getUpdateScriptUtility();
    $this->setDrupalDocrootDirPath($this->checkPackageInfo->getDrupalRootDirPath());
    $this->io = new SymfonyStyle($input, $output);
  }

  /**
   * @param array $latest_security_updates
   */
  public function updateAvailableUpdates($output, array $latest_security_updates) {
    if(count($latest_security_updates)>1){
      $this->io->note('List view of available updates.');
      $this->printPackageDetail($output);

      $this->io->note('Start package updating.');

      $this->updateScriptUtility->updateCode($latest_security_updates);

      $this->io->note('Removing downloaded files,tar.gz files');
      $this->updateScriptUtility->unlinktarfiles($latest_security_updates);
    }else{
      $this->io->success('Branch already upto date.');
    }
  }

  /**
   * Scan the directory and sub directory recorsive.
   * Get the list of all .info files list with path.
   */
  public function getInfoFilesList() {
    $dir = $this->drupalDocrootDirPath;
    $finder = new Finder();
    $finder->files()->in($dir)->name('*.info');
    foreach ($finder as $file) {
      $package_dir_path = $file->getRealPath();
      $package_dir = basename($package_dir_path);
        if(isset($this->checkPackageInfo->infoPackageFiles[$package_dir])){
            $directory_temp_path = $this->checkPackageInfo->infoPackageFiles[$package_dir].",".$package_dir_path;
            $this->checkPackageInfo->infoPackageFiles[$package_dir] = $directory_temp_path;
        }else{
          $this->checkPackageInfo->infoPackageFiles[$package_dir] = $package_dir_path;
        }
    }

  }

  /**
   * Get the detail information of info files.
   */
  public function getPackageDetailInfo() {




    foreach ($this->checkPackageInfo->infoPackageFiles as $key => $value){
        if( strpos($value,"," ) !== false ) {
            $value = explode(",",$value);
            foreach ($value as $k => $v){

                $this->checkPackageInfo->fileGetInfo($v, $key);

            }
        } else{

          $this->checkPackageInfo->fileGetInfo($value, $key);

        }
    }
  }

  /**
   * @return array
   */
  public function getAvailableUpdatesInfo() {
    return $this->checkPackageInfo->availablePackageUpdates;
  }

  /**
   * @return array
   */
  public function getInfoFiles() {
    return $this->checkPackageInfo->infoPackageFiles;
  }

  public function securityUpdateVersion() {
    $version_detail = $this->getAvailableUpdatesInfo();
    $package_info_files = $this->getInfoFiles();
    $drupal_docroot_path = $this->getDrupalDocrootDirPath();
    $git_commit_message_detail = [];
    $git_commit_message_detail[] = [
          'Package Name',
          'Package Type',
          'Current Version',
          'Latest Version',
          'Update Type',
          'Download Link',
          'File Path'
      ];
    foreach ($version_detail as $package => $versions){
      if(!isset($versions['available_versions'][0])){
        continue;
      }
      $git_commit_message['package'] = $package;
      $git_commit_message['package_type'] = $versions['package_type'];
      $git_commit_message['current_version'] = isset($versions['current_version'])?$versions['current_version']:'';
      $git_commit_message['latest_version'] = isset($versions['available_versions'][0])?$versions['available_versions'][0]['version']:'';
      $git_commit_message['update_notes'] = isset($versions['available_versions'][0]['terms'])?$this->checkPackageInfo->getUpdateType($versions['available_versions'][0]['terms']['term']):'';
      $git_commit_message['download_link'] = isset($versions['available_versions'][0])?$versions['available_versions'][0]['download_link']:'';
      if(isset($package_info_files[$package . '.info']) && is_array($package_info_files[$package . '.info'])){
        $file_paths=[];
        foreach ($package_info_files[$package . '.info'] as $p => $path_location){
          $file_path_temp =isset($path_location)?(str_replace($package . '/' . $package . '.info', '', $path_location)):'';
          if(($file_path_temp =='') && ($versions['package_type'] == 'module')){
            $file_paths[] = $drupal_docroot_path . "/sites/all/modules";
          }else{
            $file_paths[] = ($file_path_temp !='')?realpath($file_path_temp):$drupal_docroot_path;
          }
        }
        $git_commit_message['file_path'] =$file_paths;
      }else{
        $file_path =isset($package_info_files[$package . '.info'])?(str_replace($package . '/' . $package . '.info', '', $package_info_files[$package . '.info'])):'';
        $git_commit_message['file_path'] = ($file_path !='')?realpath($file_path):$drupal_docroot_path;
        // In some Cases where module name or directory name different then we pickup sites/all/modules as file path
        // ex. googleanalytics, acquia_connector etc.
        if(($file_path =='') && ($versions['package_type'] == 'module')){
          $git_commit_message['file_path'] = ($file_path !='')?realpath($file_path):$drupal_docroot_path . "/sites/all/modules";
        }
      }
      $git_commit_message_detail[] = $git_commit_message;
    }
    return $git_commit_message_detail;
  }

  public function printPackageDetail(OutputInterface $output) {

    $version_detail = $this->getAvailableUpdatesInfo();
    $table = new Table($output);
    $git_commit_message_detail=[];
    foreach ($version_detail as $package => $versions){
      if(!isset($versions['available_versions'][0])){
        continue;
      }
      $git_commit_message=[];
      $git_commit_message[] = $package;
      $git_commit_message[] = $versions['package_type'];
      $git_commit_message[] = isset($versions['current_version'])?$versions['current_version']:'';
      $git_commit_message[] = isset($versions['available_versions'][0])?$versions['available_versions'][0]['version']:'';
      $git_commit_message[] = isset($versions['available_versions'][0]['terms'])?$this->checkPackageInfo->getUpdateType($versions['available_versions'][0]['terms']['term']):'';
//      $git_commit_message[] = isset($versions['available_versions'][0])?$versions['available_versions'][0]['download_link']:'';
//      if(isset($package_info_files[$package . '.info']) && is_array($package_info_files[$package . '.info'])){
//        $file_paths=[];
//        foreach ($package_info_files[$package . '.info'] as $p => $path_location){
//          $file_path_temp =isset($path_location)?(str_replace($package . '/' . $package . '.info', '', $path_location)):'';
//          if(($file_path_temp =='') && ($versions['package_type'] == 'module')){
//            $file_paths[] = $drupal_docroot_path . "/sites/all/modules";
//          }else{
//            $file_paths[] = ($file_path_temp !='')?realpath($file_path_temp):$drupal_docroot_path;
//          }
//        }
//        $git_commit_message[] =implode(",",$file_paths);
//      }else{
//        $file_path =isset($package_info_files[$package . '.info'])?(str_replace($package . '/' . $package . '.info', '', $package_info_files[$package . '.info'])):'';
//        $file_path_temp = ($file_path !='')?realpath($file_path):$drupal_docroot_path;
//        // In some Cases where module name or directory name different then we pickup sites/all/modules as file path
//        // ex. googleanalytics, acquia_connector etc.
//        if(($file_path =='') && ($versions['package_type'] == 'module')){
//            $file_path_temp_2 = ($file_path !='')?realpath($file_path):$drupal_docroot_path . "/sites/all/modules";
//            $file_path_temp = $file_path_temp.",".$file_path_temp_2;
//        }
//          $git_commit_message[] = $file_path_temp;
//      }
      $git_commit_message_detail[] = $git_commit_message;
    }
    //print_r($git_commit_message_detail);
    $table
          ->setHeaders([
              'Package Name',
              'Package Type',
              'Current Version',
              'Latest Version',
              'Update Type'
          ])
          ->setRows($git_commit_message_detail);
    $table->render();
  }



}
