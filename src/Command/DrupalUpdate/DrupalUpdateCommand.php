<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalUpdateCommand extends CommandBase {

  /**
   * @var string
   */
  protected static $defaultName = 'app:update-d7-packages';
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
    // Validate drupal docroot or not.
    $this->validateCwdIsValidDrupalProject();
    $this->setDrupalPackageManager(new DrupalPackageManager($input, $output));

    if ($this->drupalPackagesManager->checkAvailableUpdates($this->repoRoot)) {
      $this->drupalPackagesManager->updatePackages();
      return 0;
    }
    $this->io->success('Branch already up to date.');
    return 0;
  }

}
