<?php

namespace Acquia\Cli\Command\Remote;

use AcquiaCloudApi\Endpoints\Account;
use PharData;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    $this->setDescription('Download Drush aliases for the Cloud Platform');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $alias_version = $this->promptChooseDrushAliasVersion();
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
    $base_dir = $alias_version == 8 ? '.drush' : 'sites';
    $archive = new PharData($drush_archive_filepath . '/' . $base_dir);
    $drushFiles = [];
    foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
      $drushFiles[] = $base_dir . '/' . $file->getFileName();
    }
    $archive->extractTo(dirname($drush_aliases_dir), $drushFiles, TRUE);
    $this->output->writeln(sprintf(
      'Cloud Platform Drush aliases installed into <options=bold>%s</>',
      $drush_aliases_dir
    ));
    if ($alias_version == 9) {
      $this->output->writeln('Drush 9+ does not automatically read aliases from this directory. Run <comment>drush core:init</comment> to ensure these aliases are discovered.');
      $this->output->writeln('For more details, see https://github.com/drush-ops/drush/blob/master/examples/example.site.yml');
    }
    unlink($drush_archive_filepath);

    return 0;
  }

  /**
   * Prompts the user for their preferred Drush alias version.
   */
  protected function promptChooseDrushAliasVersion() {
    $this->io->writeln('Drush changed how aliases are defined in Drush 9. Drush 8 aliases are PHP-based, while Drush 9+ aliases are YAML-based.');
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
   * @return string
   * @throws \Exception
   */
  protected function getDrushAliasesDir($version): string {
    if (!isset($this->drushAliasesDir)) {
      $this->drushAliasesDir = $this->localMachineHelper
          ->getLocalFilepath('~') . '/.drush';
      if ($version == 9) {
        $this->drushAliasesDir .= '/sites';
      }
    }

    return $this->drushAliasesDir;
  }

}
