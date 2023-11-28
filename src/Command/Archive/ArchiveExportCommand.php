<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Archive;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Attribute\RequireDb;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Closure;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[RequireAuth]
#[RequireDb]
#[AsCommand(name: 'archive:export', description: 'Export an archive of the Drupal application including code, files, and database')]
final class ArchiveExportCommand extends CommandBase {

  protected Checklist $checklist;

  private Filesystem $fs;

  /**
   * @var bool|string|string[]|null
   */
  private string|array|bool|null $destinationDir;

  private const PUBLIC_FILES_DIR = '/docroot/sites/default/files';

  protected function configure(): void {
    $this
      ->addArgument('destination-dir', InputArgument::REQUIRED, 'The destination directory for the archive file')
      ->addOption('source-dir', 'dir', InputOption::VALUE_REQUIRED, 'The directory containing the Drupal project to be pushed')
      ->addOption('no-files', NULL, InputOption::VALUE_NONE, 'Exclude public files directory from archive')
      ->addOption('no-database', 'no-db', InputOption::VALUE_NONE, 'Exclude database dump from archive');
  }

  protected function initialize(InputInterface $input, OutputInterface $output): void {
    parent::initialize($input, $output);
    $this->fs = $this->localMachineHelper->getFilesystem();
    $this->checklist = new Checklist($output);
    $this->setDirAndRequireProjectCwd($input);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->determineDestinationDir($input);
    $outputCallback = $this->getOutputCallback($output, $this->checklist);

    $randomString = (string) random_int(10000, 100000);
    $tempDirName = 'acli-archive-' . basename($this->dir) . '-' . time() . '-' . $randomString;
    $archiveTempDir = Path::join(sys_get_temp_dir(), $tempDirName);
    $this->io->confirm("This will generate a new archive in <options=bold>{$this->destinationDir}</> containing the contents of your Drupal application at <options=bold>{$this->dir}</>.\n Do you want to continue?");

    $this->checklist->addItem('Removing temporary artifact directory');
    $this->checklist->updateProgressBar("Removing $archiveTempDir");
    $this->fs->remove($archiveTempDir);
    $this->fs->mkdir([$archiveTempDir, $archiveTempDir . '/repository']);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Generating temporary archive directory');
    $this->createArchiveDirectory($archiveTempDir . '/repository');
    $this->checklist->completePreviousItem();

    if (!$input->getOption('no-database')) {
      $this->checklist->addItem('Dumping MySQL database');
      $this->exportDatabaseToArchiveDir($outputCallback, $archiveTempDir);
      $this->checklist->completePreviousItem();
    }

    $this->checklist->addItem('Compressing archive into a tarball');
    $destinationFilepath = $this->compressArchiveDirectory($archiveTempDir, $this->destinationDir, $outputCallback);
    $outputCallback('out', "Removing $archiveTempDir");
    $this->fs->remove($archiveTempDir);
    $this->checklist->completePreviousItem();

    $this->io->newLine();
    $this->io->success("An archive of your Drupal application was created at $destinationFilepath");
    if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      $this->io->note('You can download the archive through the Cloud IDE user interface by right-clicking the file in your IDE workspace file browser and selecting "Download."');
    }

    return Command::SUCCESS;
  }

  private function determineDestinationDir(InputInterface $input): void {
    $this->destinationDir = $input->getArgument('destination-dir');
    if (!$this->fs->exists($this->destinationDir)) {
      throw new AcquiaCliException("The destination directory {$this->destinationDir} does not exist!");
    }
  }

  /**
   * Build the artifact.
   */
  private function createArchiveDirectory(string $artifactDir): void {
    $this->checklist->updateProgressBar("Mirroring source files from {$this->dir} to {$artifactDir}");
    $originFinder = $this->localMachineHelper->getFinder();
    $originFinder->files()->in($this->dir)
      // Include dot files like .htaccess.
      ->ignoreDotFiles(FALSE)
      // If .gitignore exists, ignore VCS files like vendor.
      ->ignoreVCSIgnored(file_exists(Path::join($this->dir, '.gitignore')));
    if ($this->input->getOption('no-files')) {
      $this->checklist->updateProgressBar( 'Skipping ' . self::PUBLIC_FILES_DIR);
      $originFinder->exclude([self::PUBLIC_FILES_DIR]);
    }
    $targetFinder = $this->localMachineHelper->getFinder();
    $targetFinder->files()->in($artifactDir)->ignoreDotFiles(FALSE);
    $this->localMachineHelper->getFilesystem()->mirror($this->dir, $artifactDir, $originFinder, ['override' => TRUE, 'delete' => TRUE], $targetFinder);
  }

  private function exportDatabaseToArchiveDir(
    Closure $outputCallback,
    string $archiveTempDir
  ): void {
    if (!$this->getDrushDatabaseConnectionStatus($outputCallback)) {
      throw new AcquiaCliException("Could not connect to local database.");
    }
    $dumpTempFilepath = $this->createMySqlDumpOnLocal(
      $this->getLocalDbHost(),
      $this->getLocalDbUser(),
      $this->getLocalDbName(),
      $this->getLocalDbPassword(),
      $outputCallback
    );
    $dumpFilepath = Path::join($archiveTempDir, basename($dumpTempFilepath));
    $this->checklist->updateProgressBar("Moving MySQL dump to $dumpFilepath");
    $this->fs->rename($dumpTempFilepath, $dumpFilepath);
  }

  private function compressArchiveDirectory(string $archiveDir, string|bool|array|null $destinationDir, Closure $outputCallback = NULL): string {
    $destinationFilename = basename($archiveDir) . '.tar.gz';
    $destinationFilepath = Path::join($destinationDir, $destinationFilename);
    $this->localMachineHelper->checkRequiredBinariesExist(['tar']);
    $process = $this->localMachineHelper->execute(['tar', '-zcvf', $destinationFilepath, '--directory', $archiveDir, '.'], $outputCallback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to create tarball: {message}', ['message' => $process->getErrorOutput()]);
    }
    return $destinationFilepath;
  }

}
