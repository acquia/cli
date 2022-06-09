<?php


namespace Acquia\Cli\Command\DrupalUpdate;

use PharData;

class UpdateScriptUtility
{
  private $ignore_updates_files;

  /**
   * UpdateScriptUtility constructor.
   */
  public function __construct() {
    $this->ignore_updates_files = [
          '.gitignore','.htaccess','CHANGELOG.txt','sites',
      ];
  }

  /**
   * Generate tabular view of available updates
   * @param $version_detail
   */
  function printReleaseDetail($version_detail) {
    $string_count = $this->getStringCountOfColumn($version_detail);
    foreach ($version_detail as $key => $value){
      echo str_repeat("-", array_sum($string_count)+6);
      echo "\n";
      echo "| ";
      $i=0;
      foreach ($value as $v){
        if(is_array($v)){
          foreach ($v as $t => $a){
            echo $a . ",";
          }
          echo " | ";
        } else{
          $repetor = $string_count[$i]-strlen($v);
          if($repetor > 0){
            echo $v . str_repeat(" ", $string_count[$i]-strlen($v)) . " | ";
          }else{
            echo $v . str_repeat(" ", 1) . " | ";
          }

        }
        $i++;
      }
      echo "\n";
    }
    echo "\n";
  }

  /**
   * Update code based on available security update.
   * @param $latest_security_updates
   */

  function updateCode($latest_security_updates) {
    foreach ($latest_security_updates as $k => $value){
      if(!isset($value['download_link'])){
        continue;
      }
      if($value['package']=='drupal'){
        $dirname = 'temp_drupal_core';
        $filename = $value['file_path'] . "/" . $dirname . "";
        if (!file_exists($filename)) {
          $oldmask = umask(0);
          mkdir($value['file_path'] . "/" . $dirname, 0777);
          umask($oldmask);
          $value['file_path'] = $value['file_path'] . "/" . $dirname . "";
        } else {
          echo "The directory $dirname exists.";
        }
      }
      if(is_array($value['file_path'])){
        foreach ($value['file_path'] as $item) {
          $this->download_remote_file($value['package'], $value['download_link'], $item);
        }
      }else{
        $this->download_remote_file($value['package'], $value['download_link'], $value['file_path']);
      }

    }

  }

  /**
   * Download and extract tar files in given directory path.
   * @param $package
   * @param $file_url
   * @param $save_to
   */
  function download_remote_file($package, $file_url, $save_to) {
    if($package == 'drupal'){
      $this->download_remote_file_drupal_core($package, $file_url, $save_to);
      return;
    }
    $content = file_get_contents($file_url);
    file_put_contents($save_to . '/' . $package . '.tar.gz', $content);
    try {
      $phar = new PharData($save_to . '/' . $package . '.tar.gz');
      $this->rrmdir($save_to . '/' . $package);
      $phar->extractTo($save_to, NULL, TRUE); // extract all files
    } catch (\Exception $e) {
      // handle errors
    }
  }

  /**
   * Download and extract tar files in drupal core temp directory path.
   * @param $package
   * @param $file_url
   * @param $save_to
   */
  function download_remote_file_drupal_core($package, $file_url, $save_to) {
    $content = file_get_contents($file_url);
    $folder_name = str_replace('.tar.gz', '', basename($file_url));
    file_put_contents($save_to . '/' . $package . '.tar.gz', $content);
    try {
      $phar = new PharData($save_to . '/' . $package . '.tar.gz');
      $this->rrmdir($save_to . '/' . $package);
      $phar->extractTo($save_to, NULL, TRUE); // extract all files
      rename($save_to . '/' . $folder_name, $save_to . '/drupal');
    } catch (\Exception $e) {
      // handle errors
    }
    if($package == 'drupal'){
      // Replace the folder to inside docroot folder.
      $this->coreUpdate($save_to . '/drupal');
      $this->rrmdir($save_to);
    }
  }

  /**
   * Remove directory and sub directory.
   * @param $dir
   */
  function rrmdir($dir) {
    if (is_dir($dir)) {
      $objects = scandir($dir);
      foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
          if (filetype($dir . "/" . $object) == "dir")
                        $this->rrmdir($dir . "/" . $object);
          else unlink   ($dir . "/" . $object);
        }
      }
      reset($objects);
      rmdir($dir);
    }
  }

  /**
   * After extraction copy code temp to main folder replace.
   * Core modules, themes, profile.
   * @param $actual_di_path
   * @param $replace_dir_path
   */
  function coreUpdate($actual_di_path) {
    $dir    = $actual_di_path;
    $replace_dir_path = str_replace('/temp_drupal_core/drupal', '', $actual_di_path);
    $files1 = array_diff(scandir($dir), ['.', '..']);

    foreach($files1 as $r_dir => $c_dir){
      $tm_path = $dir . "/" . $c_dir;
      if(!in_array($c_dir, $this->ignore_updates_files)){
        if(is_dir($tm_path)){
          $this->custom_copy($dir . '/' . $c_dir, $replace_dir_path . '/' . $c_dir);
          continue;
        }
        rename($dir . '/' . $c_dir, $replace_dir_path . '/' . $c_dir);
      }
    }
  }

  /**
   * Copy folders and subfolder from temp to main folder.
   * @param $src
   * @param $dst
   */
  function custom_copy($src, $dst) {

    // open the source directory
    $dir = opendir($src);

    // Make the destination directory if not exist
    @mkdir($dst);

    // Loop through the files in source directory
    while( $file = readdir($dir) ) {

      if (( $file != '.' ) && ( $file != '..' )) {
        if ( is_dir($src . '/' . $file) )
                {

          // Recursively calling custom copy function
          // for sub directory
          $this->custom_copy($src . '/' . $file, $dst . '/' . $file);

        }
        else {
          copy($src . '/' . $file, $dst . '/' . $file);
        }
      }
    }

    closedir($dir);
  }

  /**
   * Remove after copy tar files and temp folder.
   * @param $remove_file_list
   */
  function unlinktarfiles($remove_file_list) {

    foreach ($remove_file_list as $k => $value){
      if( ($k == 0) || ($value['package_type']=='core') ){
        continue;
      }
      //echo $value['file_path']."/".$value['package'].".tar.gz \n";
      if(is_array($value['file_path'])){
        foreach ($value['file_path'] as $item){
          unlink($item . "/" . $value['package'] . ".tar.gz");
        }
      }else{
        unlink($value['file_path'] . "/" . $value['package'] . ".tar.gz");
      }
    }
  }

  /**
   * Compare two versions.
   * @param $v1
   * @param $v2
   * @return int
   */
  function versionCompare($v1, $v2) {
    // vnum stores each numeric
    // part of version
    $vnum1 = 0;
    $vnum2 = 0;

    // loop until both string are
    // processed
    for ($i = 0, $j = 0; ($i < strlen($v1)
          || $j < strlen($v2));) {
      // storing numeric part of
      // version 1 in vnum1
      while ($i < strlen($v1) && $v1[$i] != '.') {
        $vnum1 = $vnum1 * 10 + ( (int) ($v1[$i]) );
        $i++;
      }

      // storing numeric part of
      // version 2 in vnum2
      while ($j < strlen($v2) && $v2[$j] != '.') {
        $vnum2 = $vnum2 * 10 + ( (int) ($v2[$j]));
        $j++;
      }

      if ($vnum1 > $vnum2)
                return 1;
      if ($vnum2 > $vnum1)
                return -1;

      // if equal, reset variables and
      // go for next numeric part
      $vnum1 = $vnum2 = 0;
      $i++;
      $j++;
    }
    return 0;
  }

  /**
   * Get column wise string max charcter count.
   * @param $version_detail
   * @return array
   */
  public function getStringCountOfColumn($version_detail) {
    $temp = [];
    $string_count=[];
    $i=0;
    foreach ($version_detail as $key => $value){
      $j=0;
      foreach ($value as $k => $v){
        $temp[$j][$i]=$v;
        $j++;
      }
      $i++;
    }
    foreach ($temp as $key => $value){

      if(is_array($value)){
        $string_count[] = strlen($value[1]);
      }else{
        $string_count[] = strlen($value);
      }

    }
    return $string_count;
  }

}
