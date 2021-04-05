<?php

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Closure;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Webmozart\PathUtil\Path;

/**
 * Class PushCodeCommand.
 */
class PushCodeCommand extends PullCommandBase {

  protected static $defaultName = 'push:code';

  /**
   * @var string
   */
  protected $dir;

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Push local code to a Cloud Platform environment')
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be pushed')
      ->acceptEnvironmentId()
    ->setHelp('This command builds a sanitized deploy artifact by running composer install and removing common sensitive files. To run additional build or sanitization steps (e.g. <options=bold>npm install</>), add a <options=bold>post-install-cmd</> script to your <options=bold>composer.json</> file: https://getcomposer.org/doc/articles/scripts.md#command-events');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    // @todo change deploy strategy depending on whether this is a "source" repo (requiring building) or not
    // @todo handle if Git user name/email is missing
    $this->setDirAndRequireProjectCwd($input);
    $is_dirty = $this->isLocalGitRepoDirty();
    $commit_hash = $this->getLocalGitCommitHash();
    if ($is_dirty) {
      throw new AcquiaCliException('Pushing code was aborted because your local Git repository has uncommitted changes. Please either commit, reset, or stash your changes via git.');
    }
    $this->checklist = new Checklist($output);

    // @todo handle environments with tags deployed
    $environment = $this->determineEnvironment($input, $output, FALSE);
    $artifact_dir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $output_callback = $this->getOutputCallback($output, $this->checklist);

    $this->checklist->addItem('Preparing artifact directory');
    $this->prepareDir($output_callback, $artifact_dir, $environment->vcs->url, $environment->vcs->path);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Generating build artifact');
    $this->build($output_callback, $artifact_dir);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Sanitizing build artifact');
    $this->sanitize($artifact_dir);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem("Pushing changes to {$environment->vcs->path} branch in the {$environment->name} environment");
    $this->push($output_callback, $artifact_dir, $commit_hash);
    $this->checklist->completePreviousItem();

    return 0;
  }

  /**
   * Prepares a directory to build the artifact.
   *
   * @param \Closure $output_callback
   * @param string $artifact_dir
   * @param string $vcs_url
   * @param string $vcs_path
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function prepareDir(Closure $output_callback, string $artifact_dir, string $vcs_url, string $vcs_path): void {
    $fs = $this->localMachineHelper->getFilesystem();

    $this->logger->info("Removing $artifact_dir if it exists");
    $fs->remove($artifact_dir);

    $this->logger->info("Initializing Git in $artifact_dir");
    $process = $this->localMachineHelper->executeFromCmd("git clone --depth 1 --branch $vcs_path $vcs_url $artifact_dir", $output_callback, NULL, $this->output->isVerbose());
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Failed to clone repository from the Cloud Platform: {message}', ['message' => $process->getErrorOutput()]);
    }

    $this->logger->info('Global .gitignore file is temporarily disabled during artifact builds.');
    $this->localMachineHelper->executeFromCmd('git config --local core.excludesFile false', $output_callback, $artifact_dir, $this->output->isVerbose());
    $this->localMachineHelper->executeFromCmd('git config --local core.fileMode true', $output_callback, $artifact_dir, $this->output->isVerbose());

    // Vendor directories can be "corrupt" (i.e. missing scaffold files due to earlier sanitization) in ways that break composer install.
    $this->logger->info('Removing vendor directories');
    foreach (self::vendorDirectories() as $vendor_directory) {
      $fs->remove(Path::join($artifact_dir, $vendor_directory));
    }
  }

  protected function build(Closure $output_callback, string $artifact_dir): void {
    // @todo generate a deploy identifier
    // @see https://git.drupalcode.org/project/drupal/-/blob/9.1.x/sites/default/default.settings.php#L295
    $this->logger->info("Mirroring source files from {$this->dir} to $artifact_dir");
    $originFinder = Finder::create();
    $originFinder->files()->in($this->dir)
      // Include dot files like .htaccess.
      ->ignoreDotFiles(FALSE)
      // Ignore VCS ignored files (e.g. vendor) to speed up the mirror (Composer will restore them later).
      ->ignoreVCSIgnored(TRUE);
    $targetFinder = Finder::create();
    $targetFinder->files()->in($artifact_dir)->ignoreDotFiles(FALSE);
    $this->localMachineHelper->getFilesystem()->mirror($this->dir, $artifact_dir, $originFinder, ['override' => TRUE, 'delete' => TRUE], $targetFinder);

    $this->logger->info('Installing Composer production dependencies');
    $this->localMachineHelper->executeFromCmd('composer install --no-dev --no-interaction --optimize-autoloader', $output_callback, $artifact_dir, $this->output->isVerbose());
  }

  protected function sanitize(string $artifact_dir):void {
    $this->logger->info('Finding Drupal core text files');
    $sanitizeFinder = Finder::create()
      ->files()
      ->name('*.txt')
      ->notName('LICENSE.txt')
      ->in("$artifact_dir/docroot/core");

    $this->logger->info('Finding VCS directories');
    $vcsFinder = Finder::create()
      ->ignoreDotFiles(FALSE)
      ->ignoreVCS(FALSE)
      ->directories()
      ->in(["$artifact_dir/docroot",
        "$artifact_dir/vendor",
      ])
      ->name('.git');
    $drush_dir = "$artifact_dir/drush";
    if (file_exists($drush_dir)) {
      $vcsFinder->in($drush_dir);
    }
    if ($vcsFinder->hasResults()) {
      $sanitizeFinder->append($vcsFinder);
    }

    $this->logger->info('Finding INSTALL database text files');
    $dbInstallFinder = Finder::create()
      ->files()
      ->in([$artifact_dir])
      ->name('/INSTALL\.[a-z]+\.(md|txt)$/');
    if ($dbInstallFinder->hasResults()) {
      $sanitizeFinder->append($dbInstallFinder);
    }

    $this->logger->info('Finding other common text files');
    $filenames = [
      'AUTHORS',
      'CHANGELOG',
      'CONDUCT',
      'CONTRIBUTING',
      'INSTALL',
      'MAINTAINERS',
      'PATCHES',
      'TESTING',
      'UPDATE',
    ];
    $textFileFinder = Finder::create()
      ->files()
      ->in(["$artifact_dir/docroot"])
      ->name('/(' . implode('|', $filenames) . ')\.(md|txt)$/');
    if ($textFileFinder->hasResults()) {
      $sanitizeFinder->append($textFileFinder);
    }

    $this->logger->info("Removing sanitized files from build");
    $this->localMachineHelper->getFilesystem()->remove($sanitizeFinder);
  }

  protected function push(Closure $output_callback, string $artifact_dir, string $commit_hash):void {
    $this->logger->info('Adding and committing changed files');
    $this->localMachineHelper->executeFromCmd('git add -A', $output_callback, $artifact_dir, $this->output->isVerbose());
    foreach (self::vendorDirectories() as $vendor_directory) {
      // This will fatally error if the directory doesn't exist. Suppress error output.
      $this->localMachineHelper->executeFromCmd('git add -f ' . $vendor_directory, NULL, $artifact_dir, FALSE);
    }
    $this->localMachineHelper->executeFromCmd('git commit -m "Automated commit by Acquia CLI (source commit: ' . $commit_hash . ')"', $output_callback, $artifact_dir, $this->output->isVerbose());

    $this->logger->info('Pushing changes to Acquia Git');
    $this->localMachineHelper->executeFromCmd('git push', $output_callback, $artifact_dir, $this->output->isVerbose());
  }

  private static function vendorDirectories(): array {
    return [
      'vendor',
      'docroot/core',
      'docroot/modules/contrib',
      'docroot/themes/contrib',
      'docroot/profiles/contrib',
      'docroot/libraries',
      'drush/Commands'
    ];
  }

}
