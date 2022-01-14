<?php

namespace Acquia\Cli\Command\Remote;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Account;
use PharData;
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
      ->addOption('all-applications', 'all', InputOption::VALUE_NONE, 'Download the aliases for all applications that you have access to, not just the current one.');
    $this->acceptApplicationUuid();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $alias_version = $this->promptChooseDrushAliasVersion();
    $site_prefix = '';
    $all = $input->getOption('all');
    $application_uuid_argument = $input->getOption('applicationUuid');
    $single_application = !$all || $application_uuid_argument;
    if ($alias_version === 9 && $single_application) {
      $this->setDirAndRequireProjectCwd($input);
      $cloud_application_uuid = $this->determineCloudApplication();
      $cloud_application = $this->getCloudApplication($cloud_application_uuid);
      $parts = explode(':', $cloud_application->hosting->id);
      $site_prefix = $parts[1];
    }
    $acquia_cloud_client->addQuery('version', $alias_version);
    $account_adapter = new Account($acquia_cloud_client);
    $aliases = $account_adapter->getDrushAliases();
    $drush_archive_filepath = $this->getDrushArchiveTempFilepath();
    $this->localMachineHelper->writeFile($drush_archive_filepath, $aliases);
    $drush_aliases_dir = $this->getDrushAliasesDir($alias_version);

    // This message is useful for debugging but could be misleading in ordinary
    // usage, because the archive is deleted before the command exits.
    $this->output->writeln(sprintf(
      'Cloud Platform Drush Aliases archive downloaded to <options=bold>%s</>',
      $drush_archive_filepath
    ), OutputInterface::VERBOSITY_VERBOSE);

    $this->localMachineHelper->getFilesystem()->mkdir($drush_aliases_dir);
    $this->localMachineHelper->getFilesystem()->chmod($drush_aliases_dir, 0700);

    // Tarball may have many subdirectories, only extract this one.
    $base_dir = $alias_version === 8 ? '.drush' : 'sites';
    $archive = new PharData($drush_archive_filepath . '/' . $base_dir);
    $drushFiles = [];

    if ($single_application) {
      foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
        // Skip any alias that doesn't match the current application.
        if ($alias_version === 9 && $file->getFileName() === $site_prefix . '.site.yml') {
          $drushFiles[] = $base_dir . '/' . $file->getFileName();
          break;
        }
      }
      if (empty($drushFiles)) {
        throw new AcquiaCliException("Could not locate any aliases matching the current site ($site_prefix)");
      }
    }
    else {
      foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
        $drushFiles[] = $base_dir . '/' . $file->getFileName();
      }
    }
    $archive->extractTo(dirname($drush_aliases_dir), $drushFiles, TRUE);
    $this->output->writeln(sprintf(
      'Cloud Platform Drush aliases installed into <options=bold>%s</>',
      $drush_aliases_dir
    ));
    unlink($drush_archive_filepath);

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
          $this->drushAliasesDir = Path::join($this->dir, 'drush', 'sites');
          break;
        default:
          throw new AcquiaCliException("Unknown Drush version");
      }
    }

    return $this->drushAliasesDir;
  }

}
