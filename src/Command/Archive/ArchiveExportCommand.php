<?php

namespace Acquia\Cli\Command\Archive;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Closure;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ArchiveExportCommand extends CommandBase {

  protected Checklist $checklist;

  private Filesystem $fs;

  /**
   * @var bool|string|string[]|null
   */
  private string|array|bool|null $destinationDir;

  private const PUBLIC_FILES_DIR = '/docroot/sites/default/files';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setName('archive:export');
    $this->setDescription('Generate an archive of the Drupal application')
      ->addArgument('destination-dir', InputArgument::REQUIRED, 'The destination directory for the archive file')
      ->addOption('source-dir', 'dir', InputOption::VALUE_REQUIRED, 'The directory containing the Drupal project to be pushed')
      ->addOption('no-files', NULL, InputOption::VALUE_NONE, 'Exclude public files directory from archive')
      ->addOption('no-database', 'no-db', InputOption::VALUE_NONE, 'Exclude database dump from archive')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv())
      ->setHelp('Export an archive of the current Drupal application, including code, files, and database');
  }

  protected function initialize(InputInterface $input, OutputInterface $output): void {
    parent::initialize($input, $output);
    $this->fs = $this->localMachineHelper->getFilesystem();
    $this->checklist = new Checklist($output);
    $this->setDirAndRequireProjectCwd($input);
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->determineDestinationDir($input);
    $output_callback = $this->getOutputCallback($output, $this->checklist);

    $random_string = (string) random_int(10000, 100000);
    $temp_dir_name = 'acli-archive-' . basename($this->dir) . '-' . time() . '-' . $random_string;
    $archive_temp_dir = Path::join(sys_get_temp_dir(), $temp_dir_name);
    $this->io->confirm("This will generate a new archive in <options=bold>{$this->destinationDir}</> containing the contents of your Drupal application at <options=bold>{$this->dir}</>.\n Do you want to continue?");

    $this->checklist->addItem('Removing temporary artifact directory');
    $this->checklist->updateProgressBar("Removing $archive_temp_dir");
    $this->fs->remove($archive_temp_dir);
    $this->fs->mkdir([$archive_temp_dir, $archive_temp_dir . '/repository']);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Generating temporary archive directory');
    $this->createArchiveDirectory($archive_temp_dir . '/repository');
    $this->checklist->completePreviousItem();

    if (!$input->getOption('no-database')) {
      $this->checklist->addItem('Dumping MySQL database');
      $this->exportDatabaseToArchiveDir($output_callback, $archive_temp_dir);
      $this->checklist->completePreviousItem();
    }

    $this->checklist->addItem('Compressing archive into a tarball');
    $destination_filepath = $this->compressArchiveDirectory($archive_temp_dir, $this->destinationDir, $output_callback);
    $output_callback('out', "Removing $archive_temp_dir");
    $this->fs->remove($archive_temp_dir);
    $this->checklist->completePreviousItem();

    $this->io->newLine();
    $this->io->success("An archive of your Drupal application was created at $destination_filepath");
    if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      $this->io->note('You can download the archive through the Cloud IDE user interface by right-clicking the file in your IDE workspace file browser and selecting "Download."');
    }

    return 0;
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
  private function createArchiveDirectory(string $artifact_dir): void {
    $this->checklist->updateProgressBar("Mirroring source files from {$this->dir} to {$artifact_dir}");
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
    $targetFinder->files()->in($artifact_dir)->ignoreDotFiles(FALSE);
    $this->localMachineHelper->getFilesystem()->mirror($this->dir, $artifact_dir, $originFinder, ['override' => TRUE, 'delete' => TRUE], $targetFinder);
  }

  private function exportDatabaseToArchiveDir(
    Closure $output_callback,
    string $archive_temp_dir
  ): void {
    if (!$this->getDrushDatabaseConnectionStatus($output_callback)) {
      throw new AcquiaCliException("Could not connect to local database.");
    }
    $dump_temp_filepath = $this->createMySqlDumpOnLocal(
      $this->getDefaultLocalDbHost(),
      $this->getDefaultLocalDbUser(),
      $this->getDefaultLocalDbName(),
      $this->getDefaultLocalDbPassword(),
      $output_callback
    );
    $dump_filepath = Path::join($archive_temp_dir, basename($dump_temp_filepath));
    $this->checklist->updateProgressBar("Moving MySQL dump to $dump_filepath");
    $this->fs->rename($dump_temp_filepath, $dump_filepath);
  }

  /**
   * @param $archive_dir
   * @param $destination_dir
   */
  private function compressArchiveDirectory($archive_dir, $destination_dir, Closure $output_callback = NULL): string {
    $destination_filename = basename($archive_dir) . '.tar.gz';
    $destination_filepath = Path::join($destination_dir, $destination_filename);
    $this->localMachineHelper->checkRequiredBinariesExist(['tar']);
    $process = $this->localMachineHelper->execute(['tar', '-zcvf', $destination_filepath, '--directory', $archive_dir, '.'], $output_callback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to create tarball: {message}', ['message' => $process->getErrorOutput()]);
    }
    return $destination_filepath;
  }

}
