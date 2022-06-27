<?php


namespace Acquia\Cli\Command\DrupalUpdate;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class DrupalAllPackagesInfo
{
  /**
   * @var array
   */
  private array $packageData=[];

  /**
   * @var bool
   */
  private bool $isCoreUpdated;

  /**
   * @param bool $isCoreUpdated
   */
  public function setIsCoreUpdated(bool $isCoreUpdated): void {
    $this->isCoreUpdated = $isCoreUpdated;
  }

  /**
   * @return array
   */
  public function getPackageData(): array {
    return $this->packageData;
  }

  public function __construct(InputInterface $input,
                                OutputInterface $output) {
    $this->setIsCoreUpdated(FALSE);
  }

  /**
   * @return mixed
   */
  protected function determineCorePackageVersion() {
    $drupal_boostrap_inc_path = getcwd() . '/docroot/includes/bootstrap.inc';
    if (file_exists($drupal_boostrap_inc_path)) {
      $boostrap_inc_file_contents = file_get_contents($drupal_boostrap_inc_path);
      preg_match("/define\(\s*'([^']*)'\s*,\s*'([^']*)'\s*\)/i", $boostrap_inc_file_contents, $constraint_matches);
      if ((count($constraint_matches) > 2) && ($constraint_matches[1] == 'VERSION')) {
        return $constraint_matches[2];
      }
      else {
        return '';
      }
    }
  }

  /**
   * @return array
   */
  public function getInfoFilesList() {
    $finder = new Finder();
    $finder->files()->in(getcwd())->name('*.info');
    $info_package_files = [];
    foreach ($finder as $file) {
      $package_dir_path = $file->getRealPath();
      $package_dir = basename($package_dir_path);
      if (isset($info_package_files[$package_dir])) {
        $directory_temp_path = $info_package_files[$package_dir] . "," . $package_dir_path;
        $info_package_files[$package_dir] = $directory_temp_path;
      }
      else {
        $info_package_files[$package_dir] = $package_dir_path;
      }
    }
    return $info_package_files;
  }

  /**
   * Get the detail information of info files.
   * @param $info_packages_file_path
   */
  public function getPackageDetailInfo($info_packages_file_path) {
    foreach ($info_packages_file_path as $package_name => $package_path) {
      if ( strpos($package_path, "," ) !== FALSE ) {
        $package_path = explode(",", $package_path);
        foreach ($package_path as $path) {
          $this->fileGetInfo($path);
        }
      }
      else {
        $this->fileGetInfo($package_path);
      }
    }
  }

  /**
   * @param $filepath
   * @param $package
   */
  public function fileGetInfo($filepath) {
    $drupal_client = new DrupalOrgClient();
    $package_info_key = [
          'name',
          'description',
          'package',
          'version',
          'core',
          'project',
      ];
    set_error_handler(function () {
        // @todo when multi-dimension array in .info file.
    });
    $info_extention_file =  parse_ini_file($filepath, FALSE, INI_SCANNER_RAW);
    $current_version = '';
    $package_type = '';
    $package_alternative_name = '';
    foreach ($info_extention_file as $row => $data) {
      if (in_array(trim($row), $package_info_key)) {
        $project_value = str_replace(['\'', '"'], '', $data);
        if ( trim($row) == "project" ) {
          $package_type = trim($project_value);
        }
        if ( trim($row) == "version" ) {
          $current_version = trim($project_value);
        }
        if ( trim($row) == "package" ) {
          $package_alternative_name = strtolower(trim($project_value));
        }
      }
    }

    if ($current_version == 'VERSION') {
      $current_version = $this->determineCorePackageVersion();
    }
    if ($package_type == '') {
      $package_type = ($package_alternative_name == 'core')?'drupal':'';
    }
    if ( ($this->isCoreUpdated === FALSE) || ($package_type !== 'drupal') ) {
      $package_available_releases_data=$drupal_client->getSecurityRelease(trim($package_type), $current_version);
      if (is_array($package_available_releases_data) & !empty($package_available_releases_data)) {
        $package_name = key($package_available_releases_data);
        $this->packageData[$package_name]=$package_available_releases_data[$package_name];
      }
    }
    if ($package_type == 'drupal') {
      $this->isCoreUpdated = TRUE;
    }
    restore_error_handler();
  }

}