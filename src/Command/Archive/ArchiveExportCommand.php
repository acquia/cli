<?php

namespace Acquia\Cli\Command\Archive;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

/**
 * Class ArchiveExportCommand.
 */
class ArchiveExportCommand extends PullCommandBase {

  /**
   * @var string
   *
   * Drupal project directory.
   */
  protected $dir;

  /**
   * @var Checklist
   */
  protected $checklist;

  /**
   *
   */
  public const PUBLIC_FILES_DIR = '/docroot/sites/default/files';

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  private $fs;

  /**
   * @var bool|string|string[]|null
   */
  private $destinationDir;

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setName('archive:export');
    $this->setDescription('Generate an archive of the Drupal application')
      ->addOption('source-dir', 'dir', InputArgument::OPTIONAL, 'The directory containing the Drupal project to be pushed')
      ->addOption('destination-dir', NULL, InputOption::VALUE_REQUIRED, 'The destination directory for the archive file')
      ->addOption('no-files', NULL, InputOption::VALUE_NONE, 'Exclude public files directory from archive')
      ->addOption('no-database', 'no-db', InputOption::VALUE_NONE, 'Exclude database dump from archive')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv())
      ->setHelp('Export an archive of the current Drupal application, including code, files, and database');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->fs = $this->localMachineHelper->getFilesystem();
    $this->checklist = new Checklist($output);
    $this->setDirAndRequireProjectCwd($input);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $output_callback = $this->getOutputCallback($output, $this->checklist);

    $temp_dir_name = 'acli-archive-' . time();
    $archive_temp_dir = Path::join(sys_get_temp_dir(), $temp_dir_name);
    $this->determineDestinationDir($input);
    $this->io->confirm("This will generate a new archive in <options=bold>{$this->destinationDir}</> containing the contents of your Drupal application at <options=bold>{$this->dir}</>. Do you want to continue?");

    $this->checklist->addItem('Removing temporary artifact directory');
    $this->fs->remove($archive_temp_dir);
    $this->fs->mkdir([$archive_temp_dir, $archive_temp_dir . '/repository']);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Generating temporary archive directory');
    $this->createArchiveDirectory($output_callback, $archive_temp_dir . '/repository');
    $this->checklist->completePreviousItem();

    if (!$input->getOption('no-database')) {
      $this->checklist->addItem('Dumping MySQL database');
      $this->exportDatabaseToArchiveDir($output_callback, $archive_temp_dir);
      $this->checklist->completePreviousItem();
    }

    $this->checklist->addItem('Compressing archive into a tarball');
    $destination_filepath = $this->compressArchiveDirectory($archive_temp_dir, $this->destinationDir, $output_callback);
    $this->checklist->completePreviousItem();

    $this->printSuccessMessage($destination_filepath, $input);

    return 0;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determineDestinationDir(InputInterface $input): void {
    if ($input->getOption('destination-dir')) {
      $this->destinationDir = $input->getOption('destination-dir');
      if (!$this->fs->exists($this->destinationDir)) {
        throw new AcquiaCliException("The destination directory {$this->destinationDir} does not exist!");
      }
    }
    else {
      $this->destinationDir = Path::join([$this->dir, self::PUBLIC_FILES_DIR]);
    }
  }

  /**
   * Build the artifact.
   *
   * @param \Closure $output_callback
   * @param string $artifact_dir
   */
  protected function createArchiveDirectory(\Closure $output_callback, string $artifact_dir): void {
    $output_callback('out', "Mirroring source files from {$this->dir} to {$artifact_dir}");
    $originFinder = $this->localMachineHelper->getFinder();
    $originFinder->files()->in($this->dir)
      // Include dot files like .htaccess.
      ->ignoreDotFiles(FALSE)
      // Ignore VCS files, like .git.
      ->ignoreVCSIgnored(TRUE)
      // Ignore vendor to speed up the mirror (Composer can restore them later).
      ->exclude(['vendor']);
    if ($this->input->getOption('no-files')) {
      $output_callback('out', 'Skipping ' . self::PUBLIC_FILES_DIR);
      $originFinder->exclude([self::PUBLIC_FILES_DIR]);
    }
    $targetFinder = $this->localMachineHelper->getFinder();
    $targetFinder->files()->in($artifact_dir)->ignoreDotFiles(FALSE);
    $this->localMachineHelper->getFilesystem()->mirror($this->dir, $artifact_dir, $originFinder, ['override' => TRUE, 'delete' => TRUE], $targetFinder);
  }

  /**
   * @param \Closure $output_callback
   * @param string $archive_temp_dir
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function exportDatabaseToArchiveDir(
    \Closure $output_callback,
    string $archive_temp_dir
  ): void {
    if (!$this->getDrushDatabaseConnectionStatus($output_callback)) {
      throw new AcquiaCliException("Could not connect to local database.");
    }
    $dump_temp_filepath = $this->createMySqlDumpOnLocal(
      $this->getLocalDbHost(),
      $this->getLocalDbUser(),
      $this->getLocalDbName(),
      $this->getLocalDbPassword(),
      $output_callback
    );
    $dump_filepath = Path::join($archive_temp_dir, basename($dump_temp_filepath));
    $output_callback('out', "Moving MySQL dump to $dump_filepath");
    rename($dump_temp_filepath, $dump_filepath);
  }

  /**
   * @param $archive_dir
   * @param null $output_callback
   *
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function compressArchiveDirectory($archive_dir, $destination_dir, $output_callback = NULL): string {
    $destination_filename = basename($archive_dir) . '.tar.gz';
    $destination_filepath = Path::join([$destination_dir, $destination_filename]);
    $process = $this->localMachineHelper->execute(['tar', '-zcvf', $destination_filepath, '--directory', $archive_dir, '.'], $output_callback, NULL, $this->output->isVerbose());
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to create tarball: {message}', ['message' => $process->getErrorOutput()]);
    }
    return $destination_filepath;
  }

  /**
   * @param string $destination_filepath
   * @param \Symfony\Component\Console\Input\InputInterface $input
   */
  protected function printSuccessMessage(
    string $destination_filepath,
    InputInterface $input
  ): void {
    $url = getenv('REMOTEIDE_WEB_HOST') . str_replace($this->dir . '/docroot',
        '', $destination_filepath);
    if ($input->getOption('destination-dir')) {
      $this->io->success("An archive of your Drupal application was created at $destination_filepath");
    }
    else {
      $this->io->success("An archive of your Drupal application was created and placed in a publicly accessible location at $url");
      $this->io->warning("This file is publicly accessible. After you download it, delete it by running: \nrm $destination_filepath");
    }
  }

}
