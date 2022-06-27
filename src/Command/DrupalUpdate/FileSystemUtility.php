<?php
namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Exception\AcquiaCliException;
use PharData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class FileSystemUtility
 * @package Acquia\Cli\Command\DrupalUpdate
 */
class FileSystemUtility
{
  /**
   * @var
   */
  private Filesystem $fileSystem;

  /**
   * @var
   */
  private SymfonyStyle $io;

  /**
   * FileSystemUtility constructor.
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(InputInterface $input,
                                OutputInterface $output) {
    $this->io = new SymfonyStyle($input, $output);
    $this->fileSystem =  new Filesystem();
  }

  /**
   * Download and extract tar files in given directory path.
   * @param $package
   * @param $file_url
   * @param $save_to
   */
  function downloadRemoteFile($package, $file_url, $save_to) {
    echo $package . "==" . $file_url . "==" . $save_to . PHP_EOL;
    if ($package == 'drupal') {

      $this->downloadRemoteFileDrupalCore($package, $file_url, $save_to);
      return;
    }

    try {
      $content = file_get_contents($file_url);
      $this->fileSystem->dumpFile($save_to . '/' . $package . '.tar.gz', $content);
      $phar = new PharData($save_to . '/' . $package . '.tar.gz');
      $this->fileSystem->remove($save_to . '/' . $package);
      $phar->extractTo($save_to, NULL, TRUE);
    }
    catch (\Exception $e) {
      // @todo handle errors
      throw new AcquiaCliException("Failed to update package {$package}.");
    }
  }

  /**
   * Download and extract tar files in drupal core temp directory path.
   * @param $package
   * @param $file_url
   * @param $save_to
   * @throws AcquiaCliException
   */
  function downloadRemoteFileDrupalCore($package, $file_url, $save_to) {

    try {
      $folder_name = $this->dumpPackageTarFile($file_url, $save_to, $package);
      $phar = new PharData($save_to . '/' . $package . '.tar.gz');
      $this->fileSystem->remove($save_to . '/' . $package);
      $phar->extractTo($save_to, NULL, TRUE); // extract all files
      $this->fileSystem->rename($save_to . '/' . $folder_name, $save_to . '/drupal');
    }
    catch (\Exception $e) {
      // @todo handle errors
      throw new AcquiaCliException("Unable to download {$package} file.");
    }
    if ($package == 'drupal') {
      $this->io->note("Start core update.");
      $this->coreUpdate($save_to . '/drupal');
      $this->fileSystem->remove($save_to);
    }
  }

  /**
   * After extraction copy code temp to main folder replace.
   * Core modules, themes, profile.
   * @param $core_dir_path
   */
  function coreUpdate($core_dir_path) {
    $ignore_files = [
          '.gitignore','.htaccess','CHANGELOG.txt','sites',
      ];
    $replace_dir_path = str_replace('/temp_drupal_core/drupal', '', $core_dir_path);
    $finder = new Finder();
    $finder->in($core_dir_path)->ignoreVCSIgnored(TRUE)->notPath($ignore_files)->depth('== 0')->sortByName();
    foreach ($finder as $file) {
      $fileNameWithExtension = $file->getRelativePathname();
      $tm_path = $core_dir_path . "/" . $fileNameWithExtension;
      if (is_dir($tm_path)) {
        $this->fileSystem->mirror($core_dir_path . '/' . $fileNameWithExtension, $replace_dir_path . '/' . $fileNameWithExtension);
        continue;
      }
      $this->fileSystem->copy($core_dir_path . '/' . $fileNameWithExtension, $replace_dir_path . '/' . $fileNameWithExtension, TRUE);
    }
  }

  /**
   * Remove after copy tar files and temp folder.
   * @param $remove_file_list
   */
  function unlinkTarFiles($remove_file_list) {

    foreach ($remove_file_list as $k => $value) {
      if ( ($k == 0) || ($value['package_type']=='core') ) {
        continue;
      }
      if (is_array($value['file_path'])) {
        foreach ($value['file_path'] as $item) {
          $this->removeFile($item . "/" . $value['package'] . ".tar.gz");
        }
      }
      else {
        $this->removeFile($value['file_path'] . "/" . $value['package'] . ".tar.gz");
      }
    }
  }

  /**
   * @param $file_path
   */
  function removeFile($file_path) {
    if ($this->fileSystem->exists($file_path)) {
      $this->fileSystem->remove($file_path);
    }
    else {
      $this->io->note("File not exist for remove operation-" . $file_path);
    }
  }

  /**
   * @param $file_url
   * @param $save_to
   * @param $package
   * @return string|string[]
   * @throws AcquiaCliException
   */
  protected function dumpPackageTarFile($file_url, $save_to, $package) {
    try {
      $content = file_get_contents($file_url);
      $this->fileSystem->dumpFile($save_to . '/' . $package . '.tar.gz', $content);
      $folder_name = str_replace('.tar.gz', '', basename($file_url));
      return $folder_name;
    }
    catch (\Exception $e) {
      // @todo handle errors
      throw new AcquiaCliException("Unable to download {$package} file.");
    }
  }

}
