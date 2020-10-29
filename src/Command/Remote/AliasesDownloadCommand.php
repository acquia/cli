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
    $acquia_cloud_client->addQuery('version', 9);
    $account_adapter = new Account($acquia_cloud_client);
    $aliases = $account_adapter->getDrushAliases();
    $drush_archive_filepath = $this->getDrushArchiveTempFilepath();
    $this->localMachineHelper->writeFile($drush_archive_filepath, $aliases);
    $drush_aliases_dir = $this->getDrushAliasesDir();

    $this->output->writeln(sprintf(
      'Cloud Platform Drush Aliases archive downloaded to <options=bold>%s</>',
      $drush_archive_filepath
    ), OutputInterface::VERBOSITY_VERBOSE);

    $this->localMachineHelper->getFilesystem()->mkdir($drush_aliases_dir);
    $this->localMachineHelper->getFilesystem()->chmod($drush_aliases_dir, 0700);

    // Tarball may have many subdirectories, which one to extract?
    $base_dir = 'sites';
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
    $this->output->writeln('Drush 9+ does not automatically read aliases from this directory. Run <comment>drush core:init</comment> to ensure these aliases are discovered.');
    $this->output->writeln('For more details, see https://github.com/drush-ops/drush/blob/master/examples/example.site.yml');
    unlink($drush_archive_filepath);

    return 0;
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
  protected function getDrushAliasesDir(): string {
    if (!isset($this->drushAliasesDir)) {
      $this->drushAliasesDir = $this->localMachineHelper
          ->getLocalFilepath('~') . '/.drush/sites';
    }

    return $this->drushAliasesDir;
  }

}
