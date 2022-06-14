<?php


namespace Acquia\Cli\Command\DrupalUpdate;

use PharData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateScriptUtility
{
  /**
   * @var string[]
   */
  private array $ignoreUpdatesFiles;
  /**
   * @var SymfonyStyle
   */
  private SymfonyStyle $io;

  /**
   * UpdateScriptUtility constructor.
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->io = new SymfonyStyle($input, $output);
    $this->ignoreUpdatesFiles = [
          '.gitignore','.htaccess','CHANGELOG.txt','sites',
      ];
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
          $this->io->note("The directory $dirname exists.");
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
      // @todo handle errors
    }
    if($package == 'drupal'){
      $this->io->note("Start core update.");
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
   * @param $core_dir_path
   */
  function coreUpdate($core_dir_path) {
    $dir    = $core_dir_path;
    $replace_dir_path = str_replace('/temp_drupal_core/drupal', '', $core_dir_path);
    $files1 = array_diff(scandir($dir), ['.', '..']);

    foreach($files1 as $r_dir => $c_dir){
      $tm_path = $dir . "/" . $c_dir;
      if(!in_array($c_dir, $this->ignoreUpdatesFiles)){
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
        if ( is_dir($src . '/' . $file) ) {

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
      if(is_array($value['file_path'])){
        foreach ($value['file_path'] as $item){
          unlink($item . "/" . $value['package'] . ".tar.gz");
        }
      }else{
        unlink($value['file_path'] . "/" . $value['package'] . ".tar.gz");
      }
    }
  }

}
