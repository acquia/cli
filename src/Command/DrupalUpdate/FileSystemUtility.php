<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Exception\AcquiaCliException;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
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
class FileSystemUtility{
  /**
   * @var Filesystem
   */
  private Filesystem $fileSystem;

  /**
   * @var SymfonyStyle
   */
  private SymfonyStyle $io;

  /**
   * FileSystemUtility constructor.
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->io = new SymfonyStyle($input, $output);
    $this->fileSystem =  new Filesystem();
  }

  /**
   * @param $package
   * @param $file_url
   * @param $save_to
   * @throws AcquiaCliException
   */
  public function downloadRemoteFile($package, $file_url, $save_to) {
    if ($package == 'drupal') {
      $this->downloadRemoteFileDrupalCore($package, $file_url, $save_to);
      return;
    }

    try {
      if ($this->downloadFileGuzzleClient($file_url, $save_to . '/' . $package . '.tar.gz')) {
        $this->extractPackage($save_to, $package);
      }
    }
    catch (Exception $e) {
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
  protected function downloadRemoteFileDrupalCore($package, $file_url, $save_to) {

    try {
      $folder_name = $this->dumpPackageTarFile($file_url, $save_to, $package);
      $this->extractPackage($save_to, $package);
      $this->fileSystem->rename($save_to . '/' . $folder_name, $save_to . '/drupal');
    }
    catch (Exception $e) {
      throw new AcquiaCliException("Unable to download {$package} file.");
    }
    if ($package == 'drupal') {
      $this->io->note("Starting Drupal core update");
      $this->updateDrupalCore($save_to . '/drupal');
      $this->fileSystem->remove($save_to);
    }
  }

  /**
   * Update drupal core files with latest updates.
   * Drupal core modules, themes and profiles.
   * @param $core_dir_path
   */
  protected function updateDrupalCore($core_dir_path) {
    $ignore_files = [
        '.gitignore',
        '.htaccess',
        'CHANGELOG.txt',
        'sites',
    ];
    $replace_dir_path = str_replace('/temp_drupal_core/drupal', '', $core_dir_path);
    $finder = new Finder();
    $finder->in($core_dir_path)->ignoreVCSIgnored(TRUE)->notPath($ignore_files)->depth('== 0')->sortByName();
    foreach ($finder as $file) {
      $file_name_with_extension = $file->getRelativePathname();
      $temp_core_path = $core_dir_path . "/" . $file_name_with_extension;
      if (is_dir($temp_core_path)) {
        $this->fileSystem->mirror($temp_core_path, $replace_dir_path . '/' . $file_name_with_extension);
        continue;
      }
      $this->fileSystem->copy($temp_core_path, $replace_dir_path . '/' . $file_name_with_extension, TRUE);
    }
  }

  /**
   * Remove downloaded tar.gz files.
   * @param $remove_file_list
   */
  public function unlinkTarFiles($remove_file_list) {

    foreach ($remove_file_list as $key => $value) {
      if ( ($key == 0) || ($value['package_type'] == 'core') ) {
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
  protected function removeFile($file_path) {
    if ($this->fileSystem->exists($file_path)) {
      $this->fileSystem->remove($file_path);
    }
    else {
      $this->io->note("File " . $file_path . " does not exist.");
    }
  }

  /**
   * @param $file_url
   * @param $save_to
   * @param $package
   * @return false|string|string[]
   * @throws AcquiaCliException
   */
  protected function dumpPackageTarFile($file_url, $save_to, $package) {
    try {
      if ($this->downloadFileGuzzleClient($file_url, $save_to . '/' . $package . '.tar.gz')) {
        return str_replace('.tar.gz', '', basename($file_url));
      }
    }
    catch (Exception $e) {
      // @todo handle errors
      throw new AcquiaCliException("Unable to download {$package} file.");
    }
    return FALSE;
  }

  /**
   * @param $file_url
   * @param string $method
   * @param string $header_type
   * @return false|mixed
   * @throws AcquiaCliException
   * @throws GuzzleException
   */
  public function getFileContentsGuzzleClient($file_url, $method = 'GET', $header_type = '') {
    try {
      $client = new GuzzleClient();
      $response = $client->request($method, $file_url);

      if ($response->getStatusCode() !== 200) {
        return FALSE;
      }
      switch ($header_type) {
        case "application/xml":
          $response = simplexml_load_string($response->getBody()->getContents());
          $response = json_decode(json_encode($response), TRUE);
          break;
        default :
          $response = $response->getBody()->getContents();
          $response =  json_decode(json_encode($response), TRUE);
          break;
      }
      return $response;
    }
    catch (Exception $e) {
      throw new AcquiaCliException("Failed to read {$file_url} .");
    }
  }

  public function downloadFileGuzzleClient($file_url, $save_file_path) {
    $client = new GuzzleClient();
    try {
      $response = $client->request('GET', $file_url, ['sink' => $save_file_path]);
      if ($response->getStatusCode() !== 200) {
        throw new AcquiaCliException("Failed to download {$file_url} .");
      }
    }
    catch (GuzzleException $e) {
      throw new AcquiaCliException("Failed to download {$file_url} .");
    }
    return TRUE;
  }

  /**
   * Extract tar.gz files in package path.
   * @param $save_to
   * @param $package
   */
  protected function extractPackage($save_to, $package): void {
    $phar = new PharData($save_to . '/' . $package . '.tar.gz');
    $this->fileSystem->remove($save_to . '/' . $package);
    $phar->extractTo($save_to, NULL, TRUE);
  }

}
