<?php
namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Command\CommandBase;
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
   * @var mixed
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
    $this->validateCwdIsValidDrupalProject();
    $this->io->note('Start checking of available updates.');
    $this->setDrupalRootPath(getcwd());
    $drupal_root_path = $this->getDrupalRootPath();
    $this->setCorePackageVersion($drupal_root_path);
    $check_package_info = new CheckPackageInfo($input, $output);
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
  protected function setCorePackageVersion($drupal_root_path) {
    if (file_exists($drupal_root_path . '/docroot/includes/bootstrap.inc')) {
      $boostrap_file_contents = file_get_contents($drupal_root_path . '/docroot/includes/bootstrap.inc');
      preg_match("/define\(\s*'([^']*)'\s*,\s*'([^']*)'\s*\)/i", $boostrap_file_contents, $constraint_matches);
      if ((count($constraint_matches) > 2) && ($constraint_matches[1] == 'VERSION')) {
        $this->setDrupalCoreVersion($constraint_matches[2]);
      }
    }
  }

}
