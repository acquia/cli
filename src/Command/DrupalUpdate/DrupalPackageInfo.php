<?php
namespace Acquia\Cli\Command\DrupalUpdate;

use Composer\Semver\Comparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DrupalPackageInfo
{
  /**
   * @var array
   */
  public array $infoPackageFiles = [];




  /**
   * @var array
   */
  public array $availablePackageUpdates = [];
  /**
   * @var string
   */
  private string $drupalRootDirPath;
  /**
   * @var string
   */
  private string $drupalCoreVersion;


  /**
   * Flag for drupal core update only single time.
   * Get updates only single time of core for all core modules, themes, profile.
   * @var bool
   */
  public bool $isCoreUpdated = FALSE;

  /**
   * @var UpdateScriptUtility
   */
  private UpdateScriptUtility $updateScriptUtility;
  /**
   * @var SymfonyStyle
   */
  private SymfonyStyle $io;

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
   * DrupalPackageInfo constructor.
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(InputInterface $input,
                              OutputInterface $output) {
    $this->setUpdateScriptUtility(new UpdateScriptUtility($input, $output));
    $this->io = new SymfonyStyle($input, $output);
  }

  /**
   * @param $filepath
   * @param $package
   */
  public function fileGetInfo($filepath, $package) {
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
    $package = str_replace(".info", "", $package);
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
      $current_version = $this->getDrupalCoreVersion();
    }
    if ($package_type == '') {
      $package_type = ($package_alternative_name == 'core')?'drupal':'';
    }
    if ( ($this->isCoreUpdated === FALSE) || ($package_type !== 'drupal') ) {
      $this->getSecurityRelease(trim($package_type), $current_version);
    }
    if ($package_type == 'drupal') {
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

    if (isset($release_detail['releases']['release']) && (count($release_detail['releases']['release']) > 0 )) {
      $this->availablePackageUpdates[$project]['current_version'] = $current_version;
      $this->availablePackageUpdates[$project]['package_type'] = str_replace("project_", "", $release_detail['type']);
      for ($index = 0; $index < count($release_detail['releases']['release']); $index++) {
        $available_version = $release_detail['releases']['release'][$index]['version'];
        $version_comparision = Comparator::lessThan($current_version, $available_version);
        if ( $version_comparision !== FALSE ) {
          $this->availablePackageUpdates[$project]['available_versions'][] = $release_detail['releases']['release'][$index];
          return;
        }
        elseif ($version_comparision > 0) {
          continue;}
        else {
          return;}
      }
    }
  }

  /**
   * Multi Array with update type in response of drupal.org api.
   * @param $update_type_array
   * @return mixed|string
   */

  function getUpdateType($update_type_array) {
    if (isset($update_type_array[0]['value'])) {
      return $update_type_array[0]['value'];
    }
    elseif (isset($update_type_array['value'])) {
      return $update_type_array['value'];
    }
    return '';
  }

}
