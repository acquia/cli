<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Composer\Semver\Comparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalUpdateCommand extends  CommandBase {
  /**
   * @var string
   */
  protected static $defaultName = 'app:update-d7-packages';

  /**
   * @var mixed|null
   */
  private $drupalCoreVersion;
  /**
   * @var DrupalPackageUpdate
   */
  private DrupalPackageUpdate $drupalPackageUpdate;

  /**
   * @return DrupalPackageUpdate
   */
  public function getDrupalPackageUpdate(): DrupalPackageUpdate {
    return $this->drupalPackageUpdate;
  }

  /**
   * @param DrupalPackageUpdate $drupalPackageUpdate
   */
  public function setDrupalPackageUpdate(DrupalPackageUpdate $drupalPackageUpdate): void {
    $this->drupalPackageUpdate = $drupalPackageUpdate;
  }

  /**
   * @return mixed|null
   */
  public function getDrupalCoreVersion():  ?mixed {
    return $this->drupalCoreVersion;
  }

  /**
   * @param mixed $drupalCoreVersion
   */
  public function setDrupalCoreVersion(mixed $drupalCoreVersion): void {
    $this->drupalCoreVersion = $drupalCoreVersion;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Updates modules, themes, and distributions for a Drupal 7 application.')
         ->setHidden(TRUE);
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   *
   * @return int
   * @throws AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!$this->validateDrupal7Project()) {
      $this->io->error("Could not find a local Drupal project. Looked for `docroot/index.php` in current directories. Please execute this command from within a Drupal project directory.");
      return 1;
    }
    $this->setDrupalPackageUpdate(new DrupalPackageUpdate($input, $output));
    $detail_package_data= $this->drupalPackageUpdate->getPackagesMetaData();
    if ($this->drupalPackageUpdate->updateDrupalPackages($detail_package_data)) {
      $this->drupalPackageUpdate->printUpdatedPackageDetail($detail_package_data);
    }
    return 0;
  }

  /**
   * Ensures the application runs on Drupal 7.
   * It validate current drupal project is not d8 or d9 project.
   * @return bool
   * @throws AcquiaCliException
   */
  protected function validateDrupal7Project(): bool {
    $this->validateCwdIsValidDrupalProject();
    if ($this->repoRoot === getcwd()) {
      $process = $this->localMachineHelper->execute(['drush', 'status', 'drupal-version', '--format=json'], NULL, $this->repoRoot . '/docroot', FALSE)->enableOutput();
      if ($process->isSuccessful()) {
        $drupal_version = json_decode($process->getOutput(), TRUE);
        if (isset($drupal_version['drupal-version']) && Comparator::lessThan($drupal_version['drupal-version'], '8.0.0')) {
          $this->io->note("Current Drupal version : " . $drupal_version['drupal-version']);
          return TRUE;
        }
        else {
          throw new AcquiaCliException("Drupal 7 project not found. Current Drupal version is {$drupal_version['drupal-version']}.");
        }
      }
      else {
        throw new AcquiaCliException('Drush command not working in current directory path : {message}', ['message' => $process->getErrorOutput()]);
      }
    }
    return FALSE;
  }

}
