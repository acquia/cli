<?php


namespace Acquia\Cli\Command\DrupalUpdate;

class CheckInfo
{
  /**
   * @var array
   */
  public  $info_files = [];

  /**
   * @var array
   */
  public  $info_details_packages = [];

  /**
   * @var string[]
   */
  public  $packageinfo_key = [
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
  public $available_updates = [];

  /**
   * Flag for drupal core update only single time.
   * Get updates only single time of core for all core modules, themes, profile.
   * @var bool
   */
  public $is_core_get = FALSE;

  /**
   * @var UpdateScriptUtility
   */
  private $updateScriptUtility;

  /**
   * CheckInfo constructor.
   */
  public function __construct() {
    $this->updateScriptUtility = new UpdateScriptUtility();
  }

  /**
   * @param $files1
   * @param $dir
   */
  public function getFilesInfo($scanned_file_path, $dir) {
    foreach($scanned_file_path as $c_dir){
      $tm_path = $dir . "/" . $c_dir;
      if(is_dir($tm_path)){
        $scanned_file_path_c = array_diff(scandir($tm_path), ['.', '..']);
        $this->getFilesInfo($scanned_file_path_c, $tm_path);
      }elseif($this->endsWith($c_dir, '.info')){
        if(isset($this->info_files[$c_dir])){
          if(is_array($this->info_files[$c_dir])){
            $this->info_files[$c_dir][] = $dir . "/" . $c_dir;
          }else{
            $temp_p = $this->info_files[$c_dir];
            $this->info_files[$c_dir]=[];
            $this->info_files[$c_dir][]=$temp_p;
            $this->info_files[$c_dir][] = $dir . "/" . $c_dir;
          }
        }else{
          $this->info_files[$c_dir]=$dir . "/" . $c_dir;
        }
      }
    }
  }

  /**
   * @param $filepath
   * @param $package
   */
  public function fileGetInfo($filepath, $package) {
    $info_extention_file    = file_get_contents($filepath);
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
      if(in_array(trim($row_data[0]), $this->packageinfo_key)){
        $project_value = str_replace(['\'', '"'], '', $row_data[1]);
        $this->info_details_packages[$package][$row_data[0]]=$project_value;
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
      $current_v = VERSION;
    }
    if($package_v == ''){
      $package_v = ($package_alternet == 'core')?'drupal':'';
    }
    if( ($this->is_core_get === FALSE) || ($package_v !== 'drupal') ){
      $this->getSecurityRelease(trim($package_v), $current_v);
    }
    if($package_v == 'drupal'){
      $this->is_core_get = TRUE;
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
      $this->available_updates[$project]['current_version'] = $version1;
      $this->available_updates[$project]['package_type'] = str_replace("project_", "", $release_detail['type']);
      for ($t = 0; $t < count($release_detail['releases']['release']); $t++) {
        $version2 = $release_detail['releases']['release'][$t]['version'];
        $version_comparision = $this->updateScriptUtility->versionCompare($version1, $version2);
        if ( $version_comparision < 0 ) {
          $this->available_updates[$project]['available_versions'][] = $release_detail['releases']['release'][$t];
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
