<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalUpdateCommand extends  CommandBase {

  /**
   * @var string
   */
  protected static $defaultName = 'app:update-d7-packages';
  /**
   * @var string
   */
  private string $drupalProjectCwd;
  /**
   * @var mixed
   */
  private  $drupalCoreVersion;
  /**
   * @var PackageUpdater
   */
  private PackageUpdater $packageUpdater;

  /**
   * @var DrupalPackageManager
   */
  private DrupalPackageManager $drupalPackagesManager;

  /**
   * @return DrupalPackageManager
   */
  public function getDrupalPackagesManager(): DrupalPackageManager {
    return $this->drupalPackagesManager;
  }

  /**
   * @param $drupal_packages_manager
   */
  public function setDrupalPackageManager($drupal_packages_manager): void {
    $this->drupalPackagesManager = $drupal_packages_manager;
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

  /**
   * @return mixed
   */
  public function getDrupalCoreVersion():  mixed {
    return $this->drupalCoreVersion;
  }

  /**
   * @param mixed $drupalCoreVersion
   */
  public function setDrupalCoreVersion(mixed $drupalCoreVersion): void {
    $this->drupalCoreVersion = $drupalCoreVersion;
  }

  /**
   * @return string
   */
  public function getDrupalProjectCwd(): string {
    return $this->drupalProjectCwd;
  }

  /**
   * @param string $drupalProjectCwd
   */
  public function setDrupalProjectCwd(string $drupalProjectCwd): void {
    $this->drupalProjectCwd = $drupalProjectCwd;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Updates modules, themes, and distributions for a Drupal 7 application.')
        ->setHidden(!FileSystemUtility::determineD7App($this->repoRoot));
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   *
   * @return int
   * @throws AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->validateCwdIsValidDrupalProject();
    $this->setDrupalProjectCwd($this->repoRoot);
    $this->setDrupalPackageManager(new DrupalPackageManager($input, $output));
    $this->setPackageUpdater(new PackageUpdater($input, $output));
    $detail_package_data= $this->drupalPackagesManager->getPackagesMetaData($this->getDrupalProjectCwd());
    if (count($detail_package_data) > 1) {
      $this->packageUpdater->updateDrupalPackages($detail_package_data);
      $this->packageUpdater->printUpdatedPackageDetail($detail_package_data);
      return 0;
    }
    $this->io->success('Branch already up to date.');
    return 0;
  }

}
