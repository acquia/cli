<?php


namespace Acquia\Cli\Command\DrupalUpdate;

class UpdateScript
{
  /**
   * Ignore files and folders list.
   * @var string[]
   */
  private $ignore_updates_files = [
        '.gitignore','.htaccess','CHANGELOG.txt','sites',
    ];

  /**
   * Drupal directory folder path.
   * @var string
   */
  private $dir = DRUPAL_ROOT . "/docroot";

  /**
   * @var CheckInfo
   */
  private $checkinfo_obj;

  /**
   * @var UpdateScriptUtility
   */
  private $updateScriptUtility;

  /**
   * UpdateScript constructor.
   *
   */
  public function __construct() {
    $this->checkinfo_obj = new CheckInfo();
    $this->updateScriptUtility = new UpdateScriptUtility();
  }

  /**
   * @param array $latest_security_updates
   */
  public function updateAvailableUpdates(array $latest_security_updates) {
    if(count($latest_security_updates)>1){
      // if get any available then list out in tabular format.
      $this->updateScriptUtility->printReleaseDetail($latest_security_updates);

      // update current repo with updated code based on download and folder replace method on same path.
      $this->updateScriptUtility->updateCode($latest_security_updates);

      // remove the unwanted tar or downloaded files.
      $this->updateScriptUtility->unlinktarfiles($latest_security_updates);
    }else{
      // If no updates found.
      echo "\n Branch Upto date \n";
    }
  }

  /**
   * Scan the directory and sub directory recorsive.
   * Get the list of all .info files list with path.
   * @param string $dir
   */
  public function getInfoFilesList() {
    $dir = $this->dir;
    $scaned_files = array_diff(scandir($dir), ['.', '..']);
    foreach($scaned_files as $child_dir){
      $temp_path = $dir . "/" . $child_dir;
      if(is_dir($temp_path)){
        $scanned_file_path = array_diff(scandir($temp_path), ['.', '..']);
        $this->checkinfo_obj->getFilesInfo($scanned_file_path, $temp_path);
      }elseif($this->checkinfo_obj->endsWith($child_dir, '.info')){
        if(isset($this->checkinfo_obj->info_files[$child_dir])){
          if(is_array($this->checkinfo_obj->info_files[$child_dir])){
            $this->checkinfo_obj->info_files[$child_dir][] = $dir . "/" . $child_dir;
          }else{
            $this->checkinfo_obj->info_files[$child_dir][] = $this->checkinfo_obj->info_files[$child_dir];
            $this->checkinfo_obj->info_files[$child_dir] = $dir . "/" . $child_dir;
          }
        }else{
          $this->checkinfo_obj->info_files[$child_dir] = $dir . "/" . $child_dir;
        }

      }
    }
  }

  /**
   * Get the detail information of info files
   */
  public function getPackageDetailInfo() {
    // Get the detail information of module, theme.
    // ( package, current version, project core or contrib).
    foreach ($this->checkinfo_obj->info_files as $key => $value){
      if(is_array($value)){
        foreach ($value as $k => $v){
          $this->checkinfo_obj->fileGetInfo($v, $key);
        }
      }else{
        $this->checkinfo_obj->fileGetInfo($value, $key);
      }
    }
  }

  /**
   * @return array
   */
  public function getAvailableUpdatesinfo() {
    return $this->checkinfo_obj->available_updates;
  }

  /**
   * @return array
   */
  public function getInfoFiles() {
    return $this->checkinfo_obj->info_files;
  }

  public function securityUpdateVersion() {
    $version_detail = $this->getAvailableUpdatesinfo();
    $info_files = $this->getInfoFiles();
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
      $git_commit_message['update_notes'] = isset($versions['available_versions'][0]['terms'])?$this->checkinfo_obj->getUpdateType($versions['available_versions'][0]['terms']['term']):'';
      $git_commit_message['download_link'] = isset($versions['available_versions'][0])?$versions['available_versions'][0]['download_link']:'';
      if(isset($info_files[$package . '.info']) && is_array($info_files[$package . '.info'])){
        $file_paths=[];
        foreach ($info_files[$package . '.info'] as $p => $path_location){
          $file_path_temp =isset($path_location)?(str_replace($package . '/' . $package . '.info', '', $path_location)):'';
          if(($file_path_temp =='') && ($versions['package_type'] == 'module')){
            $file_paths[] = DRUPAL_ROOT . "/docroot/sites/all/modules";
          }else{
            $file_paths[] = ($file_path_temp !='')?realpath($file_path_temp):DRUPAL_ROOT . "/docroot";
          }
        }
        $git_commit_message['file_path'] =$file_paths;
      }else{
        $file_path =isset($info_files[$package . '.info'])?(str_replace($package . '/' . $package . '.info', '', $info_files[$package . '.info'])):'';
        $git_commit_message['file_path'] = ($file_path !='')?realpath($file_path):DRUPAL_ROOT . "/docroot";
        // In some Cases where module name or directory name different then we pickup sites/all/modules as file path
        // ex. googleanalytics, acquia_connector etc.
        if(($file_path =='') && ($versions['package_type'] == 'module')){
          $git_commit_message['file_path'] = ($file_path !='')?realpath($file_path):DRUPAL_ROOT . "/docroot/sites/all/modules";
        }
      }
      $git_commit_message_detail[] = $git_commit_message;
    }
    return $git_commit_message_detail;
  }

}
