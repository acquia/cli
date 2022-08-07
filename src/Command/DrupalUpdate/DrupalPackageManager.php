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
   * @param DrupalOrgClient $drupalOrgClient
   */
  private function setDrupalOrgClient(DrupalOrgClient $drupalOrgClient): void {
    $this->drupalOrgClient = $drupalOrgClient;
  }

  /**
   * @param bool $isCoreUpdated
   */
  private function setIsCoreUpdated(bool $isCoreUpdated): void {
    $this->isCoreUpdated = $isCoreUpdated;
  }

  /**
   * @param OutputInterface $output
   */
  private function setOutput(OutputInterface $output): void {
    $this->output = $output;
  }

  /**
   * @param FileSystemUtility $fileSystemUtility
   */
  private function setFileSystemUtility(FileSystemUtility $fileSystemUtility): void {
    $this->fileSystemUtility = $fileSystemUtility;
  }

  /**
   * @param PackageUpdater $package_updater
   */
  private function setPackageUpdater(PackageUpdater $package_updater): void {
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
  private function fetchCorePackageVersion(string $drupal_project_cwd_path): mixed {
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
  private function checkPackageInfoFileDetail(array $info_packages_file_path, string $drupal_project_cwd_path): void {
    foreach ($info_packages_file_path as $package_name => $package_path) {
      foreach ($package_path as $path) {
        $this->checkFileInfo($path, $drupal_project_cwd_path);
      }
    }
  }

  /**
   * @param string $filepath
   * @param string $drupal_project_cwd_path
   * @throws AcquiaCliException
   */
  private function checkFileInfo(string $filepath, string $drupal_project_cwd_path): void {
    $drupal_client = $this->drupalOrgClient;
    $package_info_key = [
      'name',
      'description',
      'package',
      'version',
      'core',
      'project',
    ];
    $package_parse_data = $this->fileSystemUtility->parsePackageInfoFile($filepath, $package_info_key);
    $package_data = $this->packageUpdater->preparePackageDetailData($package_parse_data, $package_info_key);
    $package_type = $package_data['package_type'];
    if ($package_type === '') {
      return;
    }
    $current_version = $package_data['current_version'];
    if ($current_version == 'VERSION') {
      $current_version = $this->fetchCorePackageVersion($drupal_project_cwd_path);
    }
    if ( ($this->isCoreUpdated === FALSE) || ($package_type !== 'drupal') ) {
      $package_available_releases_data = $drupal_client->getSecurityRelease($package_type, $current_version);
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
  private function printPackageDetail($version_detail): void {
    $table = new Table($this->output);
    $updated_packages_details = [];
    array_shift($version_detail);
    $array_keys = array_column($version_detail, 'package');
    array_multisort($array_keys, SORT_ASC, $version_detail);

    foreach ($version_detail as $versions) {
      $package = $versions['package'];
      $updated_package_data = [];
      $updated_package_data[] = $package;
      $updated_package_data[] = $versions['package_type'];
      $updated_package_data[] = $versions['current_version'] ?? '';
      $updated_package_data[] = $versions['latest_version'] ?? '';
      $updated_package_data[] = $versions['update_notes'] ?? '';
      $updated_packages_details[] = $updated_package_data;
    }
    $table->setHeaders([
          'Package Name',
          'Package Type',
          'Current Version',
          'Latest Version',
          'Update Type',
    ])->setRows($updated_packages_details);
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
    $version_detail = $this->packageData;
    $drupal_docroot_path = $drupal_project_cwd_path . '/docroot';
    return $this->prepareAvailablePackageUpdate($version_detail, $this->infoPackageFilesPath, $drupal_docroot_path);
  }

  /**
   * Get package release type from available release response.
   * Ex. bug fix, new feature, security update etc.
   * @param array $package_release_detail
   * @return string
   */
  protected function getUpdateType(array $package_release_detail): string {
    if (isset($package_release_detail[key($package_release_detail)]['value'])) {
      return $package_release_detail[key($package_release_detail)]['value'];
    }
    if (isset($package_release_detail['value'])) {
      return $package_release_detail['value'];
    }
    return "";
  }

  /**
   * Update all available packages.
   */
  public function updatePackages(): void {
    $this->packageUpdater->updatePackageCodeBase($this->detailPackageData);
    $this->fileSystemUtility->cleanupTempFiles($this->detailPackageData);
    $this->io->note('All packages have been updated.');
    $this->printPackageDetail($this->detailPackageData);
  }

  /**
   * @param array $version_detail
   * @param array $package_info_files
   * @param string $drupal_docroot_path
   *
   * @return array
   */
  protected function prepareAvailablePackageUpdate(array $version_detail, array $package_info_files, string $drupal_docroot_path): array {
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
      $available_package_detail[] = $this->preparePackageFilePaths($package_info_files, $package, $versions, $drupal_docroot_path, $package_detail);
    }
    return $available_package_detail;
  }

  /**
   * @param array $package_info_files
   * @param int|string $package
   * @param $versions
   * @param string $drupal_docroot_path
   * @param array $package_detail
   *
   * @return array
   */
  protected function preparePackageFilePaths(array $package_info_files, int|string $package, $versions, string $drupal_docroot_path, array $package_detail): array {
    if (isset($package_info_files[$package . '.info']) && is_array($package_info_files[$package . '.info'])) {
      $file_paths = [];
      foreach ($package_info_files[$package . '.info'] as $path_location) {
        $file_path_temp = isset($path_location) ? (str_replace($package . '/' . $package . '.info', '', $path_location)) : '';
        if (($file_path_temp == '') && ($versions == 'module')) {
          $file_paths[] = $drupal_docroot_path . "/sites/all/modules";
        }
        else {
          $file_paths[] = ($file_path_temp != '') ? realpath($file_path_temp) : $drupal_docroot_path;
        }
      }
      $package_detail['file_path'] = $file_paths;
    }
    if (!isset($package_detail['file_path'])) {
      $package_detail['file_path'] = [$drupal_docroot_path];
    }
    return $package_detail;
  }

}
