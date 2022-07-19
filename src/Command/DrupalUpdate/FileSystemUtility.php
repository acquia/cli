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
class FileSystemUtility {

  /**
   * @var Filesystem
   */
  private Filesystem $fileSystem;

  /**
   * @var SymfonyStyle
   */
  private SymfonyStyle $io;

  private GuzzleClient $client;

  /**
   * @return GuzzleClient
   */
  public function getClient(): GuzzleClient {
    return $this->client;
  }

  /**
   * @param GuzzleClient $client
   */
  public function setClient(GuzzleClient $client): void {
    $this->client = $client;
  }

  /**
   * FileSystemUtility constructor.
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->io = new SymfonyStyle($input, $output);
    $this->fileSystem =  new Filesystem();
    $this->setClient(new GuzzleClient());
  }

  /**
   * @param string $package
   * @param string $file_url
   * @param string $save_to
   * @throws AcquiaCliException
   */
  public function downloadRemoteFile(string $package, string $file_url, string $save_to): void {
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
      throw new AcquiaCliException("Failed to update package {$package}." . $e->getMessage());
    }
  }

  /**
   * Download and extract tar files in drupal core temp directory path.
   * @param string $package
   * @param string $file_url
   * @param string $save_to
   * @throws AcquiaCliException
   */
  protected function downloadRemoteFileDrupalCore(string $package, string $file_url, string $save_to): void {

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
   * Update Drupal core modules, themes and profiles.
   * Drupal core modules, themes and profiles.
   * @param string $core_dir_path
   */
  protected function updateDrupalCore(string $core_dir_path): void {
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
   * @param array $remove_file_list
   */
  public function unlinkTarFiles(array $remove_file_list): void {

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
   * @param string $file_path
   */
  protected function removeFile(string $file_path): void {
    if ($this->fileSystem->exists($file_path)) {
      $this->fileSystem->remove($file_path);
    }
    else {
      $this->io->note("File " . $file_path . " does not exist.");
    }
  }

  /**
   * @param string $file_url
   * @param string $save_to
   * @param string $package
   * @return false|string|string[]
   * @throws AcquiaCliException
   */
  protected function dumpPackageTarFile(string $file_url, string $save_to, string $package): false|string|array {
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
   * @param string $file_url
   * @param string $method
   * @return mixed
   * @throws AcquiaCliException
   * @throws GuzzleException
   */
  public function getFileContentsGuzzleClient(string $file_url, string $method = 'GET'): mixed {
    try {
      $client = $this->getClient();
      $response = $client->request($method, $file_url);

      if ($response->getStatusCode() !== 200) {
        return FALSE;
      }
      $response = simplexml_load_string($response->getBody()->getContents());
      $response = json_decode(json_encode($response), TRUE);

      return $response;
    }
    catch (Exception $e) {
      throw new AcquiaCliException("Failed to read {$file_url} .");
    }
  }

  /**
   * @param string $file_url
   * @param string $save_file_path
   * @return bool
   * @throws AcquiaCliException
   */
  public function downloadFileGuzzleClient(string $file_url, string $save_file_path): bool {
    $client = $this->getClient();
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
   * @param string $save_to
   * @param string $package
   */
  protected function extractPackage(string $save_to, string $package): void {
    $phar = new PharData($save_to . '/' . $package . '.tar.gz');
    $this->fileSystem->remove($save_to . '/' . $package);
    $phar->extractTo($save_to, NULL, TRUE);
  }

  /**
   * @param string $drupal_project_cwd_path
   * @return array
   * @throws AcquiaCliException
   */
  public function getInfoFilesList(string $drupal_project_cwd_path): array {
    $finder = new Finder();
    $finder->files()->in($drupal_project_cwd_path)->name('*.info');
    $info_package_files = [];
    if ($finder->count() == 0) {
      throw new AcquiaCliException("No Package Info files found.");
    }
    foreach ($finder as $file) {
      $package_dir_path = $file->getRealPath();
      $package_dir = basename($package_dir_path);
      if (isset($info_package_files[$package_dir])) {
        $directory_temp_path = $info_package_files[$package_dir] . "," . $package_dir_path;
        $info_package_files[$package_dir] = $directory_temp_path;
      }
      else {
        $info_package_files[$package_dir] = $package_dir_path;
      }
    }
    return $info_package_files;
  }

  /**
   * @param array $value
   *
   * @return array
   */
  public function getDrupalTempFolderPath(array $value): array {
    if ($value['package'] == 'drupal') {
      $dirname = 'temp_drupal_core';
      $filename = $value['file_path'] . "/" . $dirname . "";
      if (!$this->fileSystem->exists($filename)) {
        $old = umask(0);
        $this->fileSystem->mkdir($value['file_path'] . "/" . $dirname, 0777);
        umask($old);
        $value['file_path'] = $value['file_path'] . "/" . $dirname;
      }
      else {
        $this->io->note("The directory $dirname already exists.");
      }
    }
    return $value;
  }

  /**
   * @param string $drupal_project_root_path
   * @return bool
   */
  public static function determineD7App(string $drupal_project_root_path) : bool {
    if ($drupal_project_root_path == '') {
      return FALSE;
    }
    $finder = new Finder();
    $finder->files()->in($drupal_project_root_path)->notPath(['vendor'])->name('*.info');
    return !(($finder->count() == 0));
  }

}
