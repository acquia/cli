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
    $account_adapter = new Account($acquia_cloud_client);
    $aliases = $account_adapter->getDrushAliases();
    $drush_archive_filepath = $this->getDrushArchiveTempFilepath();
    $this->localMachineHelper->writeFile($drush_archive_filepath, $aliases);
    $drush_aliases_dir = $this->getDrushAliasesDir();

    $this->output->writeln(sprintf(
      'Cloud Platform Drush Aliases archive downloaded to <options=bold>%s</>',
      $drush_archive_filepath
    ));

    $this->localMachineHelper->getFilesystem()->mkdir($drush_aliases_dir);
    $this->localMachineHelper->getFilesystem()->chmod($drush_aliases_dir, 0700);

    $archive = new PharData($drush_archive_filepath . '/.drush');
    $drushFiles = [];
    foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
      $drushFiles[] = '.drush/' . $file->getFileName();
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
          ->getLocalFilepath('~') . '/.drush';
    }

    return $this->drushAliasesDir;
  }

}
