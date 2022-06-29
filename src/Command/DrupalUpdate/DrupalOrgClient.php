<?php
namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Exception\AcquiaCliException;
use Composer\Semver\Comparator;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalOrgClient
{
  /**
   * @var FileSystemUtility
   */
  private FileSystemUtility $fileSystemUtility;

  /**
   * @return FileSystemUtility
   */
  public function getFileSystemUtility(): FileSystemUtility {
    return $this->fileSystemUtility;
  }

  /**
   * @param FileSystemUtility $fileSystemUtility
   */
  public function setFileSystemUtility(FileSystemUtility $fileSystemUtility): void {
    $this->fileSystemUtility = $fileSystemUtility;
  }

  /**
   * DrupalOrgClient constructor.
   * @param FileSystemUtility $file_system_utility
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->setFileSystemUtility(new FileSystemUtility($input, $output));
  }

  /**
   * Get available updates security, bug fixes, new feature releases.
   * @param $project
   * @param $current_version
   * @throws AcquiaCliException
   */
  function getSecurityRelease($project, $current_version) {
    if ( $project === 'drupal/core') {
      $project = 'drupal';
    }
    else {
      $project = str_replace(['drupal/', 'acquia/'], '', $project);
    }
    $release_detail = $this->determineAvailablePackageReleases($project);

    if (isset($release_detail['releases']['release']) && (count($release_detail['releases']['release']) > 0 )) {
      $available_package_updates[$project]['current_version'] = $current_version;
      $available_package_updates[$project]['package_type'] = str_replace("project_", "", $release_detail['type']);
      for ($index = 0; $index < count($release_detail['releases']['release']); $index++) {
        $available_version = $release_detail['releases']['release'][$index]['version'];
        $version_comparision = Comparator::lessThan($current_version, $available_version);
        if ( $version_comparision !== FALSE ) {
          $available_package_updates[$project]['available_versions'] = $release_detail['releases']['release'][$index];
          return $available_package_updates;
        }
        elseif ($version_comparision > 0) {
          continue;
        }
        else {
          return [];
        }
      }
    }
  }

  /**
   * @param array|string $project
   * @return mixed
   * @throws AcquiaCliException
   */
  protected function determineAvailablePackageReleases(string|array $project): mixed {
    try {
      $response = $this->fileSystemUtility->fileGetContentsGuzzleClient("https://updates.drupal.org/release-history/$project/7.x/current", "GET", "application/xml");
      //      $xml = str_replace(["\n", "\r", "\t"], '', $xml);
      //      $xml = trim(str_replace('"', "'", $xml));
      //      $simpleXml = simplexml_load_string($xml);
      //      $json = json_encode($simpleXml);
      //      return json_decode($json, TRUE);

      return $response;
    }
    catch (\Exception $exception) {
      throw new AcquiaCliException("Failed to get '{$project}' package latest release data.");
    }
    catch (GuzzleException $e) {
    }

  }

}
