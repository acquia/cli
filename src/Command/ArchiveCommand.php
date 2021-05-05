<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

/**
 * Class ArchiveCommand.
 */
class ArchiveCommand extends PullCommandBase {

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
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setName('archive');
    $this->setDescription('Generate an archive of the Drupal application')
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be pushed')
      ->setHelp('')
      ->addUsage('');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->setDirAndRequireProjectCwd($input);
    $is_dirty = $this->isLocalGitRepoDirty();
    if ($is_dirty) {
      throw new AcquiaCliException('Pushing code was aborted because your local Git repository has uncommitted changes. Please either commit, reset, or stash your changes via git.');
    }
    $fs = $this->localMachineHelper->getFilesystem();
    $this->checklist = new Checklist($output);
    $temp_dir_name = 'acli-archive-' . date('u');
    $archive_dir = Path::join(sys_get_temp_dir(), $temp_dir_name);
    $output_callback = $this->getOutputCallback($output, $this->checklist);

    $this->checklist->addItem('Removing artifact directory');
    $fs->remove($archive_dir);
    $fs->mkdir($archive_dir);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Generating archive directory');
    $this->build($output_callback, $archive_dir);
    $this->checklist->completePreviousItem();

    // @todo Dump MySQL.
    // Export files.

    $this->checklist->addItem('Compressing archive into a tarball');
    $filepath_prefix = 'sites/default';
    $destination_filename = $temp_dir_name . '.tar.gz';
    $destination_filepath = $this->dir . '/docroot/' . $filepath_prefix . '/' . $destination_filename;
    $process = $this->localMachineHelper->execute(['tar', '-zcvf', $destination_filepath, $archive_dir], $output_callback, NULL, $this->output->isVerbose());
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to create tarball: {message}', ['message' => $process->getErrorOutput()]);
    }
    $this->checklist->completePreviousItem();
    $url = getenv('REMOTEIDE_WEB_HOST') . '/' . $filepath_prefix . '/' . $destination_filename;
    $this->io->success("An archive of your Drupal application is now accessible at $url");
    $this->io->warning("After you download it, delete it by running: \nrm $destination_filepath");

    return 1;
  }

  /**
   * Build the artifact.
   *
   * @param \Closure $output_callback
   * @param string $artifact_dir
   */
  protected function build(\Closure $output_callback, string $artifact_dir): void {
    $output_callback('out', "Mirroring source files from {$this->dir} to $artifact_dir");
    $originFinder = $this->localMachineHelper->getFinder();
    $originFinder->files()->in($this->dir)
      // Include dot files like .htaccess.
      ->ignoreDotFiles(FALSE)
      // Ignore VCS ignored files (e.g. vendor) to speed up the mirror (Composer can restore them later).
      ->ignoreVCSIgnored(TRUE);
    $targetFinder = $this->localMachineHelper->getFinder();
    $targetFinder->files()->in($artifact_dir)->ignoreDotFiles(FALSE);
    $this->localMachineHelper->getFilesystem()->mirror($this->dir, $artifact_dir, $originFinder, ['override' => TRUE, 'delete' => TRUE], $targetFinder);
  }

}
