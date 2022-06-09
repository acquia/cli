<?php


namespace Acquia\Cli\Command\DrupalUpdate;

use Symfony\Component\Console\Style\SymfonyStyle;

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
   * @param String $drupal_dir_path
   * @param SymfonyStyle $io
   */
  public function __construct(string $drupal_dir_path, SymfonyStyle $io, string $drupal_core_version) {
    $this->checkPackageInfo = new CheckPackageInfo($drupal_core_version);
    $this->updateScriptUtility = $this->checkPackageInfo->getUpdateScriptUtility();
    $this->setDrupalDocrootDirPath($drupal_dir_path);
    $this->io = $io;
  }

  /**
   * @param array $latest_security_updates
   */
  public function updateAvailableUpdates(array $latest_security_updates) {
    if(count($latest_security_updates)>1){
      $this->io->note('List view of available updates.');
      $this->updateScriptUtility->printReleaseDetail($latest_security_updates);

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
   *
   */
  public function getInfoFilesList() {
    $dir = $this->drupalDocrootDirPath;
    $scaned_files = array_diff(scandir($dir), ['.', '..']);
    foreach($scaned_files as $child_dir){
      $package_path = $dir . "/" . $child_dir;
      if(is_dir($package_path)){
        $scanned_file_path = array_diff(scandir($package_path), ['.', '..']);
        $this->checkPackageInfo->getFilesInfo($scanned_file_path, $package_path);
      }elseif($this->checkPackageInfo->endsWith($child_dir, '.info')){
        if(isset($this->checkPackageInfo->infoPackageFiles[$child_dir])){
          if(is_array($this->checkPackageInfo->infoPackageFiles[$child_dir])){
            $this->checkPackageInfo->infoPackageFiles[$child_dir][] = $dir . "/" . $child_dir;
          }else{
            $this->checkPackageInfo->infoPackageFiles[$child_dir][] = $this->checkPackageInfo->infoPackageFiles[$child_dir];
            $this->checkPackageInfo->infoPackageFiles[$child_dir] = $dir . "/" . $child_dir;
          }
        }else{
          $this->checkPackageInfo->infoPackageFiles[$child_dir] = $dir . "/" . $child_dir;
        }
      }
    }
  }

  /**
   * Get the detail information of info files.
   */
  public function getPackageDetailInfo() {
    foreach ($this->checkPackageInfo->infoPackageFiles as $key => $value){
      if(is_array($value)){
        foreach ($value as $k => $v){
          $this->checkPackageInfo->fileGetInfo($v, $key);
        }
      }else{
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

}
