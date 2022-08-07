<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Exception\AcquiaCliException;
use Composer\Semver\Comparator;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalOrgClient {

  /**
   * @var FileSystemUtility
   */
  private FileSystemUtility $fileSystemUtility;

  /**
   * @param FileSystemUtility $fileSystemUtility
   */
  private function setFileSystemUtility(FileSystemUtility $fileSystemUtility): void {
    $this->fileSystemUtility = $fileSystemUtility;
  }

  /**
   * DrupalOrgClient constructor.
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->setFileSystemUtility(new FileSystemUtility($input, $output));
  }

  /**
   * Get available updates: security, bug fixes or regular releases.
   * @param $project
   * @param $current_version
   * @return array
   * @throws AcquiaCliException|GuzzleException
   */
  public function getSecurityRelease($project, $current_version): array {
    if ($project === 'drupal/core') {
      $project = 'drupal';
    }
    $release_detail = $this->fetchAvailablePackageReleases($project);

    if (isset($release_detail['releases']['release'])) {
      $available_package_updates[$project]['current_version'] = $current_version;
      $available_package_updates[$project]['package_type'] = str_replace("project_", "", $release_detail['type']);
      for ($index = 0; $index < count($release_detail['releases']['release']); $index++) {
        $available_version = $release_detail['releases']['release'][$index]['version'];
        $version_comparison = Comparator::lessThan($current_version, $available_version);
        if ($version_comparison !== FALSE) {
          $available_package_updates[$project]['available_versions'] = $release_detail['releases']['release'][$index];
          return $available_package_updates;
        }
      }
    }
    return [];
  }

  /**
   * @param string $project
   * @return mixed
   * @throws AcquiaCliException|GuzzleException
   */
  private function fetchAvailablePackageReleases(string $project): mixed {
    try {
      $response = $this->fileSystemUtility->getFileContents("https://updates.drupal.org/release-history/$project/7.x/current", "GET");
      if (isset($response[key($response)]) && str_contains($response[key($response)], "No release history was found for the requested project")) {
        throw new AcquiaCliException("No release history was found for the requested project- '{$project}'.");
      }
      return $response;
    }
    catch (Exception $exception) {
      throw new AcquiaCliException("Failed to get '{$project}' package latest release data." . $exception->getMessage());
    }
  }

}
