<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckUpdatesAvailable {
  /**
   * @var SymfonyStyle
   */
  private SymfonyStyle $io;

  /**
   * @var InputInterface
   */
  private InputInterface $input;

  /**
   * @var OutputInterface
   */
  private OutputInterface $output;

  /**
   * @var array
   */
  public array $infoPackageFilesPath=[];

  /**
   * @var DrupalPackagesInfo
   */
  private DrupalPackagesInfo $packageInfo;

  /**
   * @var FileSystemUtility
   */
  private FileSystemUtility $fileSystemUtility;

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

  }

  /**
   * Get Packages detail information.
   * Package name, package type, package current version etc.
   * @param string $drupal_project_cwd_path
   * @return array
   */
  public function getPackagesMetaData(string $drupal_project_cwd_path): array {
    $this->io->note('Checking available updates.');
    $this->infoPackageFilesPath = $this->fileSystemUtility->getInfoFilesList($drupal_project_cwd_path);
    if (count($this->infoPackageFilesPath))
    $this->io->note('Preparing packages.');
    $this->packageInfo->getPackageDetailInfo($this->infoPackageFilesPath, $drupal_project_cwd_path);
    return $this->availablePackageUpdatesList($drupal_project_cwd_path);
  }

  /**
   * @param string $drupal_project_cwd_path
   * @return array
   */
  public function availablePackageUpdatesList(string $drupal_project_cwd_path): array {
    $version_detail = $this->packageInfo->getPackageData();
    $package_info_files = $this->infoPackageFilesPath;
    $drupal_docroot_path = $drupal_project_cwd_path . '/docroot';
    $git_commit_message_detail = [];
    $git_commit_message_detail[] = [
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
      $git_commit_message['package'] = $package;
      $git_commit_message['package_type'] = $versions['package_type'];
      $git_commit_message['current_version'] = $versions['current_version'] ?? '';
      $git_commit_message['latest_version'] = $versions['available_versions']['version'] ?? '';
      $git_commit_message['update_notes'] = isset($versions['available_versions']['terms']) ? $this->getUpdateType($versions['available_versions']['terms']['term']) : '';
      $git_commit_message['download_link'] = $versions['available_versions']['download_link'] ?? '';
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

}
