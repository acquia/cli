<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DrupalPackageManager {

  /**
   * @var OutputInterface
   */
  private OutputInterface $output;

  /**
   * @var array
   */
  private array $packageData=[];

  /**
   * @var bool
   */
  private bool $isCoreUpdated;

  /**
   * @var SymfonyStyle
   */
  private SymfonyStyle $io;

  /**
   * @var array
   */
  public array $infoPackageFilesPath = [];

  /**
   * @var FileSystemUtility
   */
  private FileSystemUtility $fileSystemUtility;

  /**
   * @var DrupalOrgClient
   */
  private DrupalOrgClient $drupalOrgClient;

  /**
   * @var PackageUpdater
   */
  private PackageUpdater $packageUpdater;

  /**
   * @var array
   */
  private array $detailPackageData = [];

  /**
   * @return DrupalOrgClient
   */
  public function getDrupalOrgClient(): DrupalOrgClient {
    return $this->drupalOrgClient;
  }

  /**
   * @param DrupalOrgClient $drupalOrgClient
   */
  public function setDrupalOrgClient(DrupalOrgClient $drupalOrgClient): void {
    $this->drupalOrgClient = $drupalOrgClient;
  }

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

  /**
   * @param OutputInterface $output
   */
  public function setOutput(OutputInterface $output): void {
    $this->output = $output;
  }

  /**
   * @param FileSystemUtility $fileSystemUtility
   */
  public function setFileSystemUtility(FileSystemUtility $fileSystemUtility): void {
    $this->fileSystemUtility = $fileSystemUtility;
  }

  /**
   * @return PackageUpdater
   */
  public function getPackageUpdater(): PackageUpdater {
    return $this->packageUpdater;
  }

  /**
   * @param PackageUpdater $package_updater
   */
  public function setPackageUpdater(PackageUpdater $package_updater): void {
    $this->packageUpdater = $package_updater;
  }

  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->setIsCoreUpdated(FALSE);
    $this->setDrupalOrgClient(new DrupalOrgClient($input, $output));
    $this->setOutput($output);
    $this->io = new SymfonyStyle($input, $output);
    $this->setFileSystemUtility(new FileSystemUtility($input, $output));
    $this->setPackageUpdater(new PackageUpdater($input, $output));
  }

  /**
   * @param string $drupal_project_cwd_path
   * @return mixed
   */
  protected function determineCorePackageVersion(string $drupal_project_cwd_path): mixed {
    $drupal_boostrap_inc_path = $drupal_project_cwd_path . '/docroot/includes/bootstrap.inc';
    if (file_exists($drupal_boostrap_inc_path)) {
      $boostrap_inc_file_contents = file_get_contents($drupal_boostrap_inc_path);
      preg_match("/define\(\s*'([^']*)'\s*,\s*'([^']*)'\s*\)/i", $boostrap_inc_file_contents, $constraint_matches);
      if ((count($constraint_matches) > 2) && ($constraint_matches[1] == 'VERSION')) {
        return $constraint_matches[2];
      }
    }
    return "";
  }

  /**
   * @param array $info_packages_file_path
   * @param string $drupal_project_cwd_path
   * @throws AcquiaCliException
   */
  public function checkPackageInfoFileDetail(array $info_packages_file_path, string $drupal_project_cwd_path): void {
    foreach ($info_packages_file_path as $package_name => $package_path) {
      if ( str_contains($package_path, "," ) ) {
        $package_path = explode(",", $package_path);
        foreach ($package_path as $path) {
          $this->checkFileInfo($path, $drupal_project_cwd_path);
        }
      }
      else {
        $this->checkFileInfo($package_path, $drupal_project_cwd_path);
      }
    }
  }

  /**
   * @param string $filepath
   * @param string $drupal_project_cwd_path
   * @throws AcquiaCliException
   */
  public function checkFileInfo(string $filepath, string $drupal_project_cwd_path): void {
    $drupal_client = $this->getDrupalOrgClient();
    $package_info_key = [
      'name',
      'description',
      'package',
      'version',
      'core',
      'project',
    ];
    $info_extension_file = @parse_ini_file($filepath, FALSE, INI_SCANNER_RAW);
    if (is_bool($info_extension_file) && !$info_extension_file) {
      $info_extension_file = $this->fileSystemUtility->readInfoFile($filepath);
    }
    $current_version = '';
    $package_type = '';
    $package_alternative_name = '';
    foreach ($info_extension_file as $row => $data) {

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
      $current_version = $this->determineCorePackageVersion($drupal_project_cwd_path);
    }
    if ($package_type == '') {
      $package_type = ($package_alternative_name == 'core') ? 'drupal' : '';
    }
    if ( ($this->isCoreUpdated === FALSE) || ($package_type !== 'drupal') ) {
      if (trim($package_type) === '') {
        return;
      }
      $package_available_releases_data=$drupal_client->getSecurityRelease(trim($package_type), $current_version);
      if (is_array($package_available_releases_data) & !empty($package_available_releases_data)) {
        $package_name = key($package_available_releases_data);
        $this->packageData[$package_name] = $package_available_releases_data[$package_name];
      }
    }
    if ($package_type == 'drupal') {
      $this->isCoreUpdated = TRUE;
    }
  }

  /**
   * @param $version_detail
   */
  public function printPackageDetail($version_detail): void {
    $table = new Table($this->output);
    $git_commit_message_detail = [];
    array_shift($version_detail);
    $array_keys = array_column($version_detail, 'package');
    array_multisort($array_keys, SORT_ASC, $version_detail);

    foreach ($version_detail as $versions) {
      $package = $versions['package'];
      $git_commit_message = [];
      $git_commit_message[] = $package;
      $git_commit_message[] = $versions['package_type'];
      $git_commit_message[] = $versions['current_version'] ?? '';
      $git_commit_message[] = $versions['latest_version'] ?? '';
      $git_commit_message[] = $versions['update_notes'] ?? '';
      $git_commit_message_detail[] = $git_commit_message;
    }
    $table->setHeaders([
          'Package Name',
          'Package Type',
          'Current Version',
          'Latest Version',
          'Update Type',
    ])->setRows($git_commit_message_detail);
    $table->render();
  }

  /**
   * Get Packages detail information.
   * Package name, package type, package current version etc.
   *
   * @param string $drupal_project_cwd_path
   *
   * @return bool
   * @throws AcquiaCliException
   */
  public function checkAvailableUpdates(string $drupal_project_cwd_path): bool {
    $this->io->note('Checking available updates.');
    $this->infoPackageFilesPath = $this->fileSystemUtility->getPackageInfoFilesPaths($drupal_project_cwd_path);
    if (count($this->infoPackageFilesPath) == 0) {
      throw new AcquiaCliException("Not valid Drupal 7 project.");
    }
    $this->checkPackageInfoFileDetail($this->infoPackageFilesPath, $drupal_project_cwd_path);
    $this->detailPackageData = $this->getAvailablePackageUpdates($drupal_project_cwd_path);
    return count($this->detailPackageData) > 1;
  }

  /**
   * @param string $drupal_project_cwd_path
   * @return array
   */
  public function getAvailablePackageUpdates(string $drupal_project_cwd_path): array {
    $version_detail = $this->getPackageData();
    $package_info_files = $this->infoPackageFilesPath;
    $drupal_docroot_path = $drupal_project_cwd_path . '/docroot';
    $package_detail = [];
    $available_package_detail[] = [
       'Package Name',
       'Package Type',
       'Current Version',
       'Latest Version',
       'Update Type',
       'Download Link',
       'File Path',
    ];
    foreach ($version_detail as $package => $versions) {
      if (!isset($versions['available_versions'])) {
        continue;
      }
      $package_detail['package'] = $package;
      $package_detail['package_type'] = $versions['package_type'];
      $package_detail['current_version'] = $versions['current_version'] ?? '';
      $package_detail['latest_version'] = $versions['available_versions']['version'] ?? '';
      $package_detail['update_notes'] = isset($versions['available_versions']['terms']) ? $this->getUpdateType($versions['available_versions']['terms']['term']) : '';
      $package_detail['download_link'] = $versions['available_versions']['download_link'] ?? '';
      if (isset($package_info_files[$package . '.info']) && str_contains($package_info_files[$package . '.info'], ",")) {
        $package_info_files[$package . '.info'] = explode(',', $package_info_files[$package . '.info']);
      }

      if (isset($package_info_files[$package . '.info']) && is_array($package_info_files[$package . '.info'])) {
        $file_paths=[];
        foreach ($package_info_files[$package . '.info'] as $p => $path_location) {
          $file_path_temp = isset($path_location) ? (str_replace($package . '/' . $package . '.info', '', $path_location)) : '';
          if (($file_path_temp == '') && ($versions['package_type'] == 'module')) {
            $file_paths[] = $drupal_docroot_path . "/sites/all/modules";
          }
          else {
            $file_paths[] = ($file_path_temp != '') ? realpath($file_path_temp) : $drupal_docroot_path;
          }
        }
        $package_detail['file_path'] = $file_paths;
      }
      else {
        $file_path = isset($package_info_files[$package . '.info']) ? (str_replace($package . '/' . $package . '.info', '', $package_info_files[$package . '.info'])) : '';
        $package_detail['file_path'] = ($file_path != '') ? realpath($file_path) : $drupal_docroot_path;
        if (($file_path == '') && ($versions['package_type'] == 'module')) {
          $package_detail['file_path'] = ($file_path != '') ? realpath($file_path) : $drupal_docroot_path . "/sites/all/modules";
        }
      }

      $available_package_detail[] = $package_detail;
    }
    return $available_package_detail;
  }

  /**
   * Get package release type from available release response.
   * Ex. bug fix, new feature, security update etc.
   * @param array $package_release_detail
   * @return string
   */
  protected function getUpdateType(array $package_release_detail): string {
    if (isset($package_release_detail[0]['value'])) {
      return $package_release_detail[0]['value'];
    }
    elseif (isset($package_release_detail['value'])) {
      return $package_release_detail['value'];
    }
    return "";
  }

  /**
   * Update all available packages.
   */
  public function updatePackages(): void {
    $this->io->note('Preparing packages.');
    $this->packageUpdater->initializePackageUpdate($this->detailPackageData);
    $this->printPackageDetail($this->detailPackageData);
  }

}
