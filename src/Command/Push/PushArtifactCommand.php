<?php

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Closure;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Webmozart\PathUtil\Path;

/**
 * Class PushArtifactCommand.
 */
class PushArtifactCommand extends PullCommandBase {

  protected static $defaultName = 'push:artifact';

  /**
   * @var string
   */
  protected $dir;

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Build and push a code artifact to a Cloud Platform environment')
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be pushed')
      ->addOption('no-sanitize', NULL, InputOption::VALUE_NONE, 'Do not sanitize the build artifact')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Do not push changes to Acquia Cloud')
      ->acceptEnvironmentId()
    ->setHelp('This command builds a sanitized deploy artifact by running <options=bold>composer install</>, removing sensitive files, and committing vendor directories.' . PHP_EOL . PHP_EOL
      . 'The following vendor files and directories are committed to the build artifact even if they are ignored in the source repository: ' . implode(', ', self::vendorFiles()) . PHP_EOL . PHP_EOL
      . 'To run additional build or sanitization steps (e.g. <options=bold>npm install</>), add a <options=bold>post-install-cmd</> script to your <options=bold>composer.json</> file: https://getcomposer.org/doc/articles/scripts.md#command-events');
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
    $output->writeln('<info>You must select an environment with a Git branch deployed</info>');
    $environment = $this->determineEnvironment($input, $output, TRUE);
    if (strpos($environment->vcs->path, 'tags') === 0) {
      throw new AcquiaCliException("You cannot push to an environment that has a git tag deployed to it. Environment {$environment->name} has {$environment->vcs->path} deployed. Please select a different environment.");
    }
    $artifact_dir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $output_callback = $this->getOutputCallback($output, $this->checklist);

    $this->checklist->addItem('Preparing artifact directory');
    $this->prepareDir($output_callback, $artifact_dir, $environment->vcs->url, $environment->vcs->path);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Generating build artifact');
    $this->build($output_callback, $artifact_dir);
    $this->checklist->completePreviousItem();

    if (!$input->getOption('no-sanitize')) {
      $this->checklist->addItem('Sanitizing build artifact');
      $this->sanitize($output_callback, $artifact_dir);
      $this->checklist->completePreviousItem();
    }

    $this->checklist->addItem("Committing changes (commit hash: $commit_hash)");
    $this->commit($output_callback, $artifact_dir, $commit_hash);
    $this->checklist->completePreviousItem();

    if (!$input->getOption('dry-run')) {
      $this->checklist->addItem("Pushing changes to <options=bold>{$environment->vcs->path}</> branch in the <options=bold>{$environment->name}</> environment");
      $this->push($output_callback, $artifact_dir);
      $this->checklist->completePreviousItem();
    }
    else {
      $this->logger->warning("The <options=bold>--dry-run</> option prevented changes from being pushed to Acquia Cloud. The artifact has been built at <options=bold>$artifact_dir</>");
    }

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

    $output_callback('out', "Removing $artifact_dir if it exists");
    $fs->remove($artifact_dir);

    $output_callback('out', "Initializing Git in $artifact_dir");
    $process = $this->localMachineHelper->executeFromCmd("git clone --depth 1 --branch $vcs_path $vcs_url $artifact_dir", $output_callback, NULL, $this->output->isVerbose());
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Failed to clone repository from the Cloud Platform: {message}', ['message' => $process->getErrorOutput()]);
    }

    $output_callback('out', 'Global .gitignore file is temporarily disabled during artifact builds.');
    $this->localMachineHelper->executeFromCmd('git config --local core.excludesFile false', $output_callback, $artifact_dir, $this->output->isVerbose());
    $this->localMachineHelper->executeFromCmd('git config --local core.fileMode true', $output_callback, $artifact_dir, $this->output->isVerbose());

    // Vendor directories can be "corrupt" (i.e. missing scaffold files due to earlier sanitization) in ways that break composer install.
    $output_callback('out', 'Removing vendor directories');
    foreach (self::vendorFiles() as $vendor_directory) {
      $fs->remove(Path::join($artifact_dir, $vendor_directory));
    }
  }

  protected function build(Closure $output_callback, string $artifact_dir): void {
    // @todo generate a deploy identifier
    // @see https://git.drupalcode.org/project/drupal/-/blob/9.1.x/sites/default/default.settings.php#L295
    $output_callback('out', "Mirroring source files from {$this->dir} to $artifact_dir");
    $originFinder = Finder::create();
    $originFinder->files()->in($this->dir)
      // Include dot files like .htaccess.
      ->ignoreDotFiles(FALSE)
      // Ignore VCS ignored files (e.g. vendor) to speed up the mirror (Composer will restore them later).
      ->ignoreVCSIgnored(TRUE);
    $targetFinder = Finder::create();
    $targetFinder->files()->in($artifact_dir)->ignoreDotFiles(FALSE);
    $this->localMachineHelper->getFilesystem()->mirror($this->dir, $artifact_dir, $originFinder, ['override' => TRUE, 'delete' => TRUE], $targetFinder);

    $output_callback('out', 'Installing Composer production dependencies');
    $this->localMachineHelper->executeFromCmd('composer install --no-dev --no-interaction --optimize-autoloader', $output_callback, $artifact_dir, $this->output->isVerbose());
  }

  protected function sanitize(Closure $output_callback, string $artifact_dir):void {
    $output_callback('out', 'Finding Drupal core text files');
    $sanitizeFinder = Finder::create()
      ->files()
      ->name('*.txt')
      ->notName('LICENSE.txt')
      ->in("$artifact_dir/docroot/core");

    $output_callback('out', 'Finding VCS directories');
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

    $output_callback('out', 'Finding INSTALL database text files');
    $dbInstallFinder = Finder::create()
      ->files()
      ->in([$artifact_dir])
      ->name('/INSTALL\.[a-z]+\.(md|txt)$/');
    if ($dbInstallFinder->hasResults()) {
      $sanitizeFinder->append($dbInstallFinder);
    }

    $output_callback('out', 'Finding other common text files');
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

    $output_callback('out', "Removing sanitized files from build");
    $this->localMachineHelper->getFilesystem()->remove($sanitizeFinder);
  }

  protected function commit(Closure $output_callback, string $artifact_dir, string $commit_hash):void {
    $output_callback('out', 'Adding and committing changed files');
    $this->localMachineHelper->executeFromCmd('git add -A', $output_callback, $artifact_dir, $this->output->isVerbose());
    foreach (self::vendorFiles() as $vendor_directory) {
      // This will fatally error if the directory doesn't exist. Suppress error output.
      $this->logger->debug("Forcibly adding $vendor_directory");
      $this->localMachineHelper->executeFromCmd('git add -f ' . $vendor_directory, NULL, $artifact_dir, FALSE);
    }
    $this->localMachineHelper->executeFromCmd('git commit -m "Automated commit by Acquia CLI (source commit: ' . $commit_hash . ')"', $output_callback, $artifact_dir, $this->output->isVerbose());
  }

  protected function push(Closure $output_callback, string $artifact_dir):void {
    $output_callback('out', 'Pushing changes to Acquia Git');
    $this->localMachineHelper->executeFromCmd('git push', $output_callback, $artifact_dir, $this->output->isVerbose());
  }

  private static function vendorFiles(): array {
    return [
      'vendor',
      'docroot/core',
      'docroot/modules/contrib',
      'docroot/themes/contrib',
      'docroot/profiles/contrib',
      'docroot/libraries',
      'drush/Commands',
      'docroot/index.php',
      'docroot/.htaccess',
      'docroot/autoload.php',
      'docroot/robots.txt',
      'docroot/update.php',
      'docroot/web.config'
    ];
  }

}
