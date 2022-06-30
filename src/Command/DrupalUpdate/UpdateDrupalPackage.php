<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class UpdateDrupalPackage{
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
   * @var DrupalPackagesInfo
   */
  private DrupalPackagesInfo $drupalPackagesInfo;

  /**
   * @return DrupalPackagesInfo
   */
  public function getDrupalPackagesInfo(): DrupalPackagesInfo {
    return $this->drupalPackagesInfo;
  }

  /**
   * @param $drupalPackagesInfo
   */
  public function setDrupalPackagesInfo($drupalPackagesInfo): void {
    $this->drupalPackagesInfo = $drupalPackagesInfo;
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
    $this->io = new SymfonyStyle($input, $output);
    $this->setFileSystemUtility(new FileSystemUtility($input, $output));
    $this->fileSystem = new Filesystem();
    $this->setDrupalPackagesInfo(new DrupalPackagesInfo($input, $output));
  }

  /**
   * @param array $latest_security_updates
   * @throws AcquiaCliException
   */
  public function updateDrupalPackages(array $latest_security_updates): void {
    $this->io->note('Starting package update process.');
    $this->updatePackageCode($latest_security_updates);
    $this->fileSystemUtility->unlinkTarFiles($latest_security_updates);
    $this->io->note('All packages have been updated.');
  }

  /**
   * @param $latest_security_updates
   * @throws AcquiaCliException
   */
  public function updatePackageCode($latest_security_updates): void {
    foreach ($latest_security_updates as $k => $value) {
      if (!isset($value['download_link'])) {
        continue;
      }
      $this->updateDrupalPackageCode($value);
    }
  }

  /**
   * @param $value
   * @throws AcquiaCliException
   */
  protected function updateDrupalPackageCode($value): void {
    $value = $this->fileSystemUtility->getDrupalTempFolderPath($value);
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
    $this->getDrupalPackagesInfo()->printPackageDetail($latest_security_updates);
  }

}
