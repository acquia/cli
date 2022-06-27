<?php
namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Composer\Semver\Comparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalUpdateCommand extends  CommandBase
{
  /**
   * @var string
   */
  protected static $defaultName = 'app:update-d7-packages';

  /**
   * @var mixed|null
   */
  private  $drupalCoreVersion;

  /**
   * @return mixed
   */
  public function getDrupalCoreVersion() {
    return $this->drupalCoreVersion;
  }

  /**
   * @param mixed $drupalCoreVersion
   */
  public function setDrupalCoreVersion($drupalCoreVersion): void {
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
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    if (!$this->drupalProjectValidation()) {
      $this->io->error("Could not find a local Drupal project. Looked for `docroot/index.php` in current directories. Please execute this command from within a Drupal project directory.");
      return 1;
    }

    $drupal_package_update = new DrupalPackageUpdate($input, $output);
    $detail_package_data= $drupal_package_update->getPackagesMetaData();

    if ($drupal_package_update->packageUpdate($detail_package_data)) {
      $drupal_package_update->printUpdatedPackageDetail($detail_package_data);
    }

    return 0;
  }

  /**
   * @return bool
   * @throws AcquiaCliException
   */
  protected function drupalProjectValidation() {
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
          throw new AcquiaCliException("Drupal 7 project not found, current drupal version - {$drupal_version['drupal-version']}");
        }
      }
      else {
        $this->io->error("Drush command not working in current directory path.");
      }
    }
    return FALSE;
  }

}
