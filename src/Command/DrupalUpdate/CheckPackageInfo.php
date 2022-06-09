<?php


namespace Acquia\Cli\Command\DrupalUpdate;

use Composer\Semver\Comparator;

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

  public string $drupalCoreVersion;

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

  /**
   * CheckInfo constructor.
   */
  public function __construct($drupal_core_version) {
    $this->setUpdateScriptUtility(new UpdateScriptUtility());
    $this->setDrupalCoreVersion($drupal_core_version);
  }

  /**
   * @param $files1
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

    // @todo parse_ini_file($filepath);

    $info_extention_file = file_get_contents($filepath, TRUE);
    $rows = explode("\n", $info_extention_file);
    $current_v = '';
    $package_v = '';
    $package_alternet = '';
    foreach($rows as $row => $data) {
      // If no key value exist in info file.
      if (strpos($data, '=') === FALSE){
        continue;
      }
      //get raw data in key value pair with seprator.
      $row_data = explode('=', $data);
      $package = str_replace(".info", "", $package);
      if(in_array(trim($row_data[0]), $this->packageInfoKey)){
        $project_value = str_replace(['\'', '"'], '', $row_data[1]);
        $this->infoDetailPackages[$package][$row_data[0]]=$project_value;
        if( trim($row_data[0]) == "project" ){
          $package_v = trim($project_value);
        }
        if( trim($row_data[0]) == "version" ){
          $current_v = trim($project_value);
        }
        if( trim($row_data[0]) == "package" ){
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
        if ( $version_comparision < 0 ) {
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
