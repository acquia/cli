<?php


namespace Acquia\Cli\Command\DrupalUpdate;

use Composer\Semver\Comparator;
use PHPUnit\Framework\Warning;

class CheckPackageInfo
{
  /**
   * @var array
   */
  public array $infoPackageFiles = [];

  /**
   * @var array
   */
  public array $infoDetailPackages = [];

  /**
   * @var string[]
   */
  public array $packageInfoKey = [
        'name',
        'description',
        'package',
        'version',
        'core',
        'project',
    ];

  /**
   * @var array
   */
  public array $availablePackageUpdates = [];


  /**
   * Flag for drupal core update only single time.
   * Get updates only single time of core for all core modules, themes, profile.
   * @var bool
   */
  public $isCoreUpdated = FALSE;

  /**
   * @var UpdateScriptUtility
   */
  private $updateScriptUtility;

  /**
   * @return UpdateScriptUtility
   */
  public function getUpdateScriptUtility(): UpdateScriptUtility {
    return $this->updateScriptUtility;
  }

  /**
   * @param UpdateScriptUtility $updateScriptUtility
   */
  public function setUpdateScriptUtility(UpdateScriptUtility $updateScriptUtility): void {
    $this->updateScriptUtility = $updateScriptUtility;
  }

  private $drupalRootDirPath;

  private $drupalCoreVersion;

  /**
   * @return mixed
   */
  public function getDrupalCoreVersion() {
    return $this->drupalCoreVersion;
  }

  /**
   * @param mixed $drupalCoreVersion
   */
  public function setDrupalCoreVersion($drupalCoreVersion): void {
    $this->drupalCoreVersion = $drupalCoreVersion;
  }

  /**
   * @return mixed
   */
  public function getDrupalRootDirPath() {
    return $this->drupalRootDirPath;
  }

  /**
   * @param mixed $drupalRootDirPath
   */
  public function setDrupalRootDirPath($drupalRootDirPath): void {
    $this->drupalRootDirPath = $drupalRootDirPath;
  }

  /**
   * CheckPackageInfo constructor.
   */
  public function __construct() {
    $this->setUpdateScriptUtility(new UpdateScriptUtility());
  }

  /**
   * @param $scanned_file_path
   * @param $package_dir
   */
  public function getFilesInfo($scanned_file_path, $package_dir) {
    foreach($scanned_file_path as $package_dir_path){
      $full_package_path = $package_dir . "/" . $package_dir_path;
      if(is_dir($full_package_path)){
        $scanned_file_path_c = array_diff(scandir($full_package_path), ['.', '..']);
        $this->getFilesInfo($scanned_file_path_c, $full_package_path);
      }elseif($this->endsWith($package_dir_path, '.info')){
        if(isset($this->infoPackageFiles[$package_dir_path])){
          if(is_array($this->infoPackageFiles[$package_dir_path])){
            $this->infoPackageFiles[$package_dir_path][] = $package_dir . "/" . $package_dir_path;
          }else{
            $temp_p = $this->infoPackageFiles[$package_dir_path];
            $this->infoPackageFiles[$package_dir_path]=[];
            $this->infoPackageFiles[$package_dir_path][]=$temp_p;
            $this->infoPackageFiles[$package_dir_path][] = $package_dir . "/" . $package_dir_path;
          }
        }else{
          $this->infoPackageFiles[$package_dir_path]=$package_dir . "/" . $package_dir_path;
        }
      }
    }
  }

  /**
   * @param $filepath
   * @param $package
   */
  public function fileGetInfo($filepath, $package) {
    set_error_handler(function() {
        // @todo when multidimension array in .info file.
         });
    $info_extention_file =  parse_ini_file($filepath,false,INI_SCANNER_RAW) ;

    $current_v = '';
    $package_v = '';
    $package_alternet = '';
    $package = str_replace(".info", "", $package);
    foreach($info_extention_file as $row => $data) {
      //get raw data in key value pair with seprator.

      if(in_array(trim($row), $this->packageInfoKey)){
        $project_value = str_replace(['\'', '"'], '', $data);
        $this->infoDetailPackages[$package][$row]=$project_value;
        if( trim($row) == "project" ){
          $package_v = trim($project_value);
        }
        if( trim($row) == "version" ){
          $current_v = trim($project_value);
        }
        if( trim($row) == "package" ){
          $package_alternet = strtolower(trim($project_value));
        }
      }
    }

    // When Drupal Core version is defined in bootstrap.inc file.
    if($current_v == 'VERSION'){
      $current_v = $this->getDrupalCoreVersion();
    }
    if($package_v == ''){
      $package_v = ($package_alternet == 'core')?'drupal':'';
    }
    if( ($this->isCoreUpdated === FALSE) || ($package_v !== 'drupal') ){
      $this->getSecurityRelease(trim($package_v), $current_v);
    }
    if($package_v == 'drupal'){
      $this->isCoreUpdated = TRUE;
    }
      restore_error_handler();
  }

  /**
   * Get available updates security, bug fixes, new feature releases.
   * @param $project
   * @param $current_version
   */
  function getSecurityRelease($project, $current_version) {
    if ( $project === 'drupal/core') {
      $project = 'drupal';
    }
    else {
      $project = str_replace(['drupal/', 'acquia/'], '', $project);
    }
    $xml = file_get_contents("https://updates.drupal.org/release-history/$project/7.x/current");
    $xml = str_replace(["\n", "\r", "\t"], '', $xml);
    $xml = trim(str_replace('"', "'", $xml));
    $simpleXml = simplexml_load_string($xml);
    $json = json_encode($simpleXml);
    $release_detail = json_decode($json, TRUE);

    if(isset($release_detail['releases']['release']) && (count($release_detail['releases']['release']) > 0 )) {
      $version1 = $current_version;
      $this->availablePackageUpdates[$project]['current_version'] = $version1;
      $this->availablePackageUpdates[$project]['package_type'] = str_replace("project_", "", $release_detail['type']);
      for ($t = 0; $t < count($release_detail['releases']['release']); $t++) {
        $version2 = $release_detail['releases']['release'][$t]['version'];
        $version_comparision = Comparator::lessThan($version1, $version2);
        if ( $version_comparision !== FALSE ) {
          $this->availablePackageUpdates[$project]['available_versions'][] = $release_detail['releases']['release'][$t];
          return;
        }
        elseif ($version_comparision > 0){continue;}
        else {return;}
      }
    }
  }

  /**
   * Multi Array with update type in response of drupal.org api.
   * @param $update_type_array
   * @return mixed|string
   */

  function getUpdateType($update_type_array) {
    if(isset($update_type_array[0]['value'])){
      return $update_type_array[0]['value'];
    }elseif(isset($update_type_array['value'])){
      return $update_type_array['value'];
    }
    return '';
  }

  /**
   * Check file extentions .info or not.
   * @param $string
   * @param $endString
   * @return bool
   */
  function endsWith($string, $endString) {
    $len = strlen($endString);
    if ($len == 0) {
      return TRUE;
    }
    return (substr($string, -$len) === $endString);
  }

}
