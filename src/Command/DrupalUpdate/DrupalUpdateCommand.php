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
   * @var mixed
   */
  private $drupalRootPath;
  /**
   * @var mixed|null
   */
  private  $drupalCoreVersion;

  /**
   * @return mixed
   */
  public function getDrupalRootPath() {
    return $this->drupalRootPath;
  }

  /**
   * @param mixed $drupalRootPath
   */
  public function setDrupalRootPath($drupalRootPath): void {
    $this->drupalRootPath = $drupalRootPath;
  }

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

    $this->io->note('Start checking of available updates.');
    $this->setDrupalRootPath(getcwd());
    $drupal_root_path = $this->getDrupalRootPath();
    $this->determineCorePackageVersion($drupal_root_path);
    $check_package_info = new DrupalPackageInfo($input, $output);
    $check_package_info->setDrupalRootDirPath($drupal_root_path);
    $check_package_info->setDrupalCoreVersion($this->getDrupalCoreVersion());
    $package_update_script = new PackageUpdateScript($input, $output, $check_package_info);

    $this->io->note('Reading all packages.');
    $package_update_script->getInfoFilesList();

    $this->io->note('Preparing all packages detail list(package name, package type,current version etc.).');
    $package_update_script->getPackageDetailInfo();

    $this->io->note('Checking available updates of packages.');
    $latest_updates = $package_update_script->securityUpdateVersion();

    $package_update_script->updateAvailableUpdates($output, $latest_updates);
    return 0;
  }

  /**
   * @param $drupal_root_path
   * @param $constraint_matches
   * @return mixed
   */
  protected function determineCorePackageVersion($drupal_root_path) {
    if (file_exists($drupal_root_path . '/docroot/includes/bootstrap.inc')) {
      $boostrap_file_contents = file_get_contents($drupal_root_path . '/docroot/includes/bootstrap.inc');
      preg_match("/define\(\s*'([^']*)'\s*,\s*'([^']*)'\s*\)/i", $boostrap_file_contents, $constraint_matches);
      if ((count($constraint_matches) > 2) && ($constraint_matches[1] == 'VERSION')) {
        $this->setDrupalCoreVersion($constraint_matches[2]);
      }
    }
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
