<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class DrupalPackageUpdate{

  /**
   * @var array
   */
  public array $infoPackageFilesPath=[];

  /**
   * @var DrupalPackagesInfo
   */
  private DrupalPackagesInfo $packageInfo;

  /**
   * @var SymfonyStyle
   */
  private SymfonyStyle $io;

  /**
   * @var FileSystemUtility
   */
  private FileSystemUtility $fileSystemUtility;

  /**
   * @var Filesystem
   */
  private Filesystem $fileSystem;

  /**
   * @var InputInterface
   */
  private InputInterface $input;

  /**
   * @var OutputInterface
   */
  private OutputInterface $output;

  /**
   * @return DrupalPackagesInfo
   */
  public function getPackageInfo(): DrupalPackagesInfo {
    return $this->packageInfo;
  }

  /**
   * @param DrupalPackagesInfo $packageInfo
   */
  public function setPackageInfo(DrupalPackagesInfo $packageInfo): void {
    $this->packageInfo = $packageInfo;
  }

  /**
   * @return FileSystemUtility
   */
  public function getFileSystemUtility(): FileSystemUtility {
    return $this->fileSystemUtility;
  }

  /**
   * @param FileSystemUtility $fileSystemUtility
   */
  public function setFileSystemUtility(FileSystemUtility $fileSystemUtility): void {
    $this->fileSystemUtility = $fileSystemUtility;
  }

  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $this->setPackageInfo(new DrupalPackagesInfo($input, $output));
    $this->io = new SymfonyStyle($input, $output);
    $this->setFileSystemUtility(new FileSystemUtility($input, $output));
    $this->fileSystem = new Filesystem();
  }

  /**
   * Get Packages detail information.
   * Package name, package type, package current version etc.
   * @return array
   */
  public function getPackagesMetaData() {
    $this->io->note('Checking available updates.');
    $this->infoPackageFilesPath = $this->packageInfo->getInfoFilesList();
    $this->io->note('Preparing packages.');
    try {
      $this->packageInfo->getPackageDetailInfo($this->infoPackageFilesPath);
    }
    catch (AcquiaCliException $e) {
      // @todo handle exception.
    }
    return $this->availablePackageUpdatesList();
  }

  /**
   * @return array
   */
  public function availablePackageUpdatesList() {
    $version_detail = $this->packageInfo->getPackageData();
    $package_info_files = $this->infoPackageFilesPath;
    $drupal_docroot_path = getcwd() . '/docroot';
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
    foreach ($version_detail as $package => $versions) {
      if (!isset($versions['available_versions'])) {
        continue;
      }
      $git_commit_message['package'] = $package;
      $git_commit_message['package_type'] = $versions['package_type'];
      $git_commit_message['current_version'] = isset($versions['current_version']) ? $versions['current_version'] : '';
      $git_commit_message['latest_version'] = isset($versions['available_versions']) ? $versions['available_versions']['version'] : '';
      $git_commit_message['update_notes'] = isset($versions['available_versions']['terms']) ? $this->getUpdateType($versions['available_versions']['terms']['term']) : '';
      $git_commit_message['download_link'] = isset($versions['available_versions']) ? $versions['available_versions']['download_link'] : '';
      if (isset($package_info_files[$package . '.info']) && strpos($package_info_files[$package . '.info'], ",") !== FALSE ) {
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
        $git_commit_message['file_path'] = $file_paths;
      }
      else {
        $file_path = isset($package_info_files[$package . '.info']) ? (str_replace($package . '/' . $package . '.info', '', $package_info_files[$package . '.info'])) : '';
        $git_commit_message['file_path'] = ($file_path != '') ? realpath($file_path) : $drupal_docroot_path;
        if (($file_path == '') && ($versions['package_type'] == 'module')) {
          $git_commit_message['file_path'] = ($file_path != '') ? realpath($file_path) : $drupal_docroot_path . "/sites/all/modules";
        }
      }

      $git_commit_message_detail[] = $git_commit_message;
    }
    return $git_commit_message_detail;
  }

  /**
   * @param array $latest_security_updates
   * @return bool
   * @throws AcquiaCliException
   */
  public function updateDrupalPackages(array $latest_security_updates) {

    if (count($latest_security_updates) > 1) {
      $this->io->note('Starting package update process.');
      $this->updatePackageCode($latest_security_updates);
      $this->fileSystemUtility->unlinkTarFiles($latest_security_updates);
      $this->io->note('All packages have been updated.');

      return TRUE;
    }

    $this->io->success('Branch already up to date.');
    return FALSE;
  }

  /**
   * @param $latest_security_updates
   * @throws AcquiaCliException
   */

  public function updatePackageCode($latest_security_updates) {
    foreach ($latest_security_updates as $k => $value) {
      if (!isset($value['download_link'])) {
        continue;
      }
      $this->updateDrupalPackageCode($value);
    }
  }

  /**
   * Get package release type from available release response.
   * Ex. bug fix, new feature, security update etc.
   * @param array $package_release_detail
   * @return mixed|string
   */

  protected function getUpdateType(array $package_release_detail) {
    if (isset($package_release_detail[0]['value'])) {
      return $package_release_detail[0]['value'];
    }
    elseif (isset($package_release_detail['value'])) {
      return $package_release_detail['value'];
    }
    return '';
  }

  /**
   * @param $value
   * @throws AcquiaCliException
   */
  protected function updateDrupalPackageCode($value) {
    if ($value['package'] == 'drupal') {
      $dirname = 'temp_drupal_core';
      $filename = $value['file_path'] . "/" . $dirname . "";
      if (!$this->fileSystem->exists($filename)) {
        $old = umask(0);
        $this->fileSystem->mkdir($value['file_path'] . "/" . $dirname, 0777);
        umask($old);
        $value['file_path'] = $value['file_path'] . "/" . $dirname . "";
      }
      else {
        $this->io->note("The directory $dirname already exists.");
      }
    }
    if (is_array($value['file_path'])) {
      foreach ($value['file_path'] as $item) {
        $this->fileSystemUtility->downloadRemoteFile($value['package'], $value['download_link'], $item);
      }
    }
    else {
      $this->fileSystemUtility->downloadRemoteFile($value['package'], $value['download_link'], $value['file_path']);
    }

  }

  /**
   * @param array $latest_security_updates
   */
  public function printUpdatedPackageDetail(array $latest_security_updates): void {
    $updated_package_information = new UpdatedPackagesInfo($this->output);
    $updated_package_information->printPackageDetail($latest_security_updates);
  }

}
