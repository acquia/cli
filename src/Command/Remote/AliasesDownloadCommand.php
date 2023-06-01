<?php

namespace Acquia\Cli\Command\Remote;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Account;
use PharData;
use Psr\Http\Message\StreamInterface;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

/**
 * A command to proxy Drush commands on an environment using SSH.
 */
class AliasesDownloadCommand extends SshCommand {

  private string $drushArchiveFilepath;

  /**
   * @var string
   */
  protected static $defaultName = 'remote:aliases:download';

  protected function configure(): void {
    $this->setDescription('Download Drush aliases for the Cloud Platform')
      ->addOption('destination-dir', NULL, InputOption::VALUE_REQUIRED, 'The directory to which aliases will be downloaded')
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Download the aliases for all applications that you have access to, not just the current one.');
    $this->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $aliasVersion = $this->promptChooseDrushAliasVersion();
    $drushArchiveTempFilepath = $this->getDrushArchiveTempFilepath();
    $drushAliasesDir = $this->getDrushAliasesDir($aliasVersion);
    $this->localMachineHelper->getFilesystem()->mkdir($drushAliasesDir);
    $this->localMachineHelper->getFilesystem()->chmod($drushAliasesDir, 0700);

    if ($aliasVersion === '9') {
      $this->downloadDrush9Aliases($input, $aliasVersion, $drushArchiveTempFilepath, $drushAliasesDir);
    }
    else {
      $this->downloadDrush8Aliases($aliasVersion, $drushArchiveTempFilepath, $drushAliasesDir);
    }

    $this->output->writeln(sprintf(
      'Cloud Platform Drush aliases installed into <options=bold>%s</>',
      $drushAliasesDir
    ));
    unlink($drushArchiveTempFilepath);

    return Command::SUCCESS;
  }

  /**
   * Prompts the user for their preferred Drush alias version.
   */
  protected function promptChooseDrushAliasVersion(): string {
    $this->io->writeln('Drush changed how aliases are defined in Drush 9. Drush 8 aliases are PHP-based and stored in your home directory, while Drush 9+ aliases are YAML-based and stored with your project.');
    $question = 'Choose your preferred alias compatibility:';
    $choices = [
      '8' => 'Drush 8 / Drupal 7 (PHP)',
      '9' => 'Drush 9+ / Drupal 8+ (YAML)',
    ];
    return array_search($this->io->choice($question, $choices, '9'), $choices, TRUE);
  }

  public function getDrushArchiveTempFilepath(): string {
    if (!isset($this->drushArchiveFilepath)) {
      $this->drushArchiveFilepath = tempnam(sys_get_temp_dir(),
          'AcquiaDrushAliases') . '.tar.gz';
    }

    return $this->drushArchiveFilepath;
  }

  protected function getDrushAliasesDir(string $version): string {
    if ($this->input->getOption('destination-dir')) {
      return $this->input->getOption('destination-dir');
    }
    return match ($version) {
      '8' => Path::join($this->localMachineHelper::getHomeDir(), '.drush'),
      '9' => Path::join($this->getProjectDir(), 'drush'),
      default => throw new AcquiaCliException("Unknown Drush version"),
    };
  }

  protected function getAliasesFromCloud(Client $acquiaCloudClient, int $aliasVersion): StreamInterface {
    $acquiaCloudClient->addQuery('version', $aliasVersion);
    return (new Account($acquiaCloudClient))->getDrushAliases();
  }

  protected function getSitePrefix(bool $singleApplication): string {
    $sitePrefix = '';
    if ($singleApplication) {
      $cloudApplicationUuid = $this->determineCloudApplication();
      $cloudApplication = $this->getCloudApplication($cloudApplicationUuid);
      $parts = explode(':', $cloudApplication->hosting->id);
      $sitePrefix = $parts[1];
    }
    return $sitePrefix;
  }

  protected function downloadArchive(int $aliasVersion, string $drushArchiveTempFilepath, string $baseDir): PharData {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $aliases = $this->getAliasesFromCloud($acquiaCloudClient, $aliasVersion);
    $this->localMachineHelper->writeFile($drushArchiveTempFilepath, $aliases);
    return new PharData($drushArchiveTempFilepath . '/' . $baseDir);
  }

  protected function downloadDrush9Aliases(InputInterface $input, int $aliasVersion, string $drushArchiveTempFilepath, string $drushAliasesDir): void {
    $this->setDirAndRequireProjectCwd($input);
    $all = $input->getOption('all');
    $applicationUuidArgument = $input->getArgument('applicationUuid');
    $singleApplication = !$all || $applicationUuidArgument;
    $sitePrefix = $this->getSitePrefix($singleApplication);
    $baseDir = 'sites';
    $archive = $this->downloadArchive($aliasVersion, $drushArchiveTempFilepath, $baseDir);
    if ($singleApplication) {
      $drushFiles = $this->getSingleAliasForSite($archive, $sitePrefix, $baseDir);
    }
    else {
      $drushFiles = [];
      foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
        $drushFiles[] = $baseDir . '/' . $file->getFileName();
      }
    }
    $archive->extractTo($drushAliasesDir, $drushFiles, TRUE);
  }

  protected function downloadDrush8Aliases(int $aliasVersion, string $drushArchiveTempFilepath, string $drushAliasesDir): void {
    $baseDir = '.drush';
    $archive = $this->downloadArchive($aliasVersion, $drushArchiveTempFilepath, $baseDir);
    $drushFiles = [];
    foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
      $drushFiles[] = $baseDir . '/' . $file->getFileName();
    }
    $archive->extractTo($drushAliasesDir, $drushFiles, TRUE);
  }

  /**
   * @return array
   */
  protected function getSingleAliasForSite(PharData $archive, string $sitePrefix, string $baseDir): array {
    $drushFiles = [];
    foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
      // Just get the single alias for this single application.
      if ($file->getFileName() === $sitePrefix . '.site.yml') {
        $drushFiles[] = $baseDir . '/' . $file->getFileName();
        break;
      }
    }
    if (empty($drushFiles)) {
      throw new AcquiaCliException("Could not locate any aliases matching the current site ($sitePrefix)");
    }
    return $drushFiles;
  }

}
