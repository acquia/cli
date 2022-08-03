<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class PackageUpdater {

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
   * @param FileSystemUtility $file_system_utility
   */
  public function setFileSystemUtility(FileSystemUtility $file_system_utility): void {
    $this->fileSystemUtility = $file_system_utility;
  }

  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $this->io = new SymfonyStyle($input, $output);
    $this->setFileSystemUtility(new FileSystemUtility($input, $output));
    $this->fileSystem = new Filesystem();
  }

  /**
   * @param array $latest_security_updates
   * @throws AcquiaCliException
   */
  public function updatePackageCodeBase(array $latest_security_updates): void {
    foreach ($latest_security_updates as $value) {
      if (isset($value['download_link'])) {
        $this->updatePackageCode($value);
      }

    }
  }

  /**
   * @param array $value
   * @throws AcquiaCliException
   */
  protected function updatePackageCode(array $value): void {
    $value = $this->fileSystemUtility->getDrupalTempFolderPath($value);
    if (isset($value['file_path']) && is_array($value['file_path'])) {
      foreach ($value['file_path'] as $filepath) {
        $this->fileSystemUtility->downloadRemoteFile($value['package'], $value['download_link'], $filepath);
      }
    }
  }

  /**
   * @param array $package_parse_data
   * @param array $package_info_key
   *
   * @return array
   */
  public function preparePackageDetailData(array $package_parse_data): array {
    $current_version = isset($package_parse_data['version']) ? trim(str_replace(['\'', '"'], '', $package_parse_data['version'])) : '';
    $package_type = isset($package_parse_data['project']) ? trim(str_replace(['\'', '"'], '', $package_parse_data['project'])) : '';
    $package_alternative_name = isset($package_parse_data['package']) ? strtolower(trim(str_replace(['\'', '"'], '', $package_parse_data['package']))) : '';
    if ($package_type == '') {
      $package_type = ($package_alternative_name == 'core') ? 'drupal' : '';
    }
    return [
      'current_version' => $current_version,
      'package_type' => $package_type
    ];
  }

}
