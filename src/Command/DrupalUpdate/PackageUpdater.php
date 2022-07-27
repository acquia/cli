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
  public function initializePackageUpdate(array $latest_security_updates): void {
    $this->io->note('Starting package update process.');
    $this->updatePackageCodeBase($latest_security_updates);
    $this->fileSystemUtility->unlinkTarFiles($latest_security_updates);
    $this->io->note('All packages have been updated.');
  }

  /**
   * @param array $latest_security_updates
   * @throws AcquiaCliException
   */
  public function updatePackageCodeBase(array $latest_security_updates): void {
    foreach ($latest_security_updates as $k => $value) {
      if (!isset($value['download_link'])) {
        continue;
      }
      $this->updatePackageCode($value);
    }
  }

  /**
   * @param array $value
   * @throws AcquiaCliException
   */
  protected function updatePackageCode(array $value): void {
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

}
