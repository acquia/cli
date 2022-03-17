<?php

namespace Acquia\Cli\Command\Remote;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Account;
use PharData;
use Psr\Http\Message\StreamInterface;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

/**
 * Class DrushCommand
 * A command to proxy Drush commands on an environment using SSH.
 *
 * @package Acquia\Cli\Commands\Remote
 */
class AliasesDownloadCommand extends SshCommand {

  /**
   * @var string
   */
  private $drushArchiveFilepath;

  /** @var string */
  private $drushAliasesDir;

  protected static $defaultName = 'remote:aliases:download';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Download Drush aliases for the Cloud Platform')
      ->addOption('destination-dir', NULL, InputOption::VALUE_REQUIRED, 'The directory to which aliases will be downloaded')
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Download the aliases for all applications that you have access to, not just the current one.');
    $this->acceptApplicationUuid();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $alias_version = $this->promptChooseDrushAliasVersion();
    $drush_archive_temp_filepath = $this->getDrushArchiveTempFilepath();
    $drush_aliases_dir = $this->getDrushAliasesDir($alias_version);
    $this->localMachineHelper->getFilesystem()->mkdir($drush_aliases_dir);
    $this->localMachineHelper->getFilesystem()->chmod($drush_aliases_dir, 0700);

    if ($alias_version === 9) {
      $this->downloadDrush9Aliases($input, $alias_version, $drush_archive_temp_filepath, $drush_aliases_dir);
    }
    else {
      $this->downloadDrush8Aliases($alias_version, $drush_archive_temp_filepath, $drush_aliases_dir);
    }

    $this->output->writeln(sprintf(
      'Cloud Platform Drush aliases installed into <options=bold>%s</>',
      $drush_aliases_dir
    ));
    unlink($drush_archive_temp_filepath);

    return 0;
  }

  /**
   * Prompts the user for their preferred Drush alias version.
   */
  protected function promptChooseDrushAliasVersion() {
    $this->io->writeln('Drush changed how aliases are defined in Drush 9. Drush 8 aliases are PHP-based and stored in your home directory, while Drush 9+ aliases are YAML-based and stored with your project.');
    $question = 'Choose your preferred alias compatibility:';
    $choices = [
      8 => 'Drush 8 / Drupal 7 (PHP)',
      9 => 'Drush 9+ / Drupal 8+ (YAML)',
    ];
    return array_search($this->io->choice($question, $choices, '9'), $choices);
  }

  /**
   * @param string $drushAliasesDir
   */
  public function setDrushAliasesDir(string $drushAliasesDir): void {
    $this->drushAliasesDir = $drushAliasesDir;
  }

  /**
   * @return string
   */
  public function getDrushArchiveTempFilepath(): string {
    if (!isset($this->drushArchiveFilepath)) {
      $this->drushArchiveFilepath = tempnam(sys_get_temp_dir(),
          'AcquiaDrushAliases') . '.tar.gz';
    }

    return $this->drushArchiveFilepath;
  }

  /**
   * @param string $version
   *
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getDrushAliasesDir(string $version): string {
    if ($this->input->getOption('destination-dir')) {
      $this->drushAliasesDir = $this->input->getOption('destination-dir');
    }
    elseif (!isset($this->drushAliasesDir)) {
      switch ($version) {
        case 8:
          $this->drushAliasesDir = $this->localMachineHelper
              ->getLocalFilepath('~') . '/.drush';
          break;
        case 9:
          $this->drushAliasesDir = Path::join($this->getRepoRoot(), 'drush');
          break;
        default:
          throw new AcquiaCliException("Unknown Drush version");
      }
    }

    return $this->drushAliasesDir;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param int $alias_version
   *
   * @return \Psr\Http\Message\StreamInterface
   */
  protected function getAliasesFromCloud(Client $acquia_cloud_client, $alias_version): StreamInterface {
    $acquia_cloud_client->addQuery('version', $alias_version);
    $account_adapter = new Account($acquia_cloud_client);
    return $account_adapter->getDrushAliases();
  }

  /**
   * @param bool $single_application
   *
   * @return mixed|string
   * @throws \Exception
   */
  protected function getSitePrefix(bool $single_application) {
    $site_prefix = '';
    if ($single_application) {
      $cloud_application_uuid = $this->determineCloudApplication();
      $cloud_application = $this->getCloudApplication($cloud_application_uuid);
      $parts = explode(':', $cloud_application->hosting->id);
      $site_prefix = $parts[1];
    }
    return $site_prefix;
  }

  /**
   * @param int $alias_version
   * @param string $drush_archive_temp_filepath
   * @param string $base_dir
   *
   * @return \PharData
   */
  protected function downloadArchive($alias_version, string $drush_archive_temp_filepath, string $base_dir): PharData {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $aliases = $this->getAliasesFromCloud($acquia_cloud_client, $alias_version);
    $this->localMachineHelper->writeFile($drush_archive_temp_filepath, $aliases);
    return new PharData($drush_archive_temp_filepath . '/' . $base_dir);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param int $alias_version
   * @param string $drush_archive_temp_filepath
   * @param string $drush_aliases_dir
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function downloadDrush9Aliases(InputInterface $input, int $alias_version, string $drush_archive_temp_filepath, string $drush_aliases_dir): void {
    $this->setDirAndRequireProjectCwd($input);
    $all = $input->getOption('all');
    $application_uuid_argument = $input->getArgument('applicationUuid');
    $single_application = !$all || $application_uuid_argument;
    $site_prefix = $this->getSitePrefix($single_application);
    $base_dir = 'sites';
    $archive = $this->downloadArchive($alias_version, $drush_archive_temp_filepath, $base_dir);
    if ($single_application) {
      $drushFiles = $this->getSingleAliasForSite($archive, $site_prefix, $base_dir);
    }
    else {
      $drushFiles = [];
      foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
        $drushFiles[] = $base_dir . '/' . $file->getFileName();
      }
    }
    $archive->extractTo($drush_aliases_dir, $drushFiles, TRUE);
  }

  /**
   * @param int $alias_version
   * @param string $drush_archive_temp_filepath
   * @param string $drush_aliases_dir
   */
  protected function downloadDrush8Aliases($alias_version, string $drush_archive_temp_filepath, string $drush_aliases_dir): void {
    $base_dir = '.drush';
    $archive = $this->downloadArchive($alias_version, $drush_archive_temp_filepath, $base_dir);
    $drushFiles = [];
    foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
      $drushFiles[] = $base_dir . '/' . $file->getFileName();
    }
    $archive->extractTo($drush_aliases_dir, $drushFiles, TRUE);
  }

  /**
   * @param \PharData $archive
   * @param string $site_prefix
   * @param string $base_dir
   *
   * @return array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getSingleAliasForSite(PharData $archive, $site_prefix, string $base_dir): array {
    $drushFiles = [];
    foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
      // Just get the single alias for this single application.
      if ($file->getFileName() === $site_prefix . '.site.yml') {
        $drushFiles[] = $base_dir . '/' . $file->getFileName();
        break;
      }
    }
    if (empty($drushFiles)) {
      throw new AcquiaCliException("Could not locate any aliases matching the current site ($site_prefix)");
    }
    return $drushFiles;
  }

}
