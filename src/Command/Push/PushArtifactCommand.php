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
use Webmozart\PathUtil\Path;

/**
 * Class PushArtifactCommand.
 */
class PushArtifactCommand extends PullCommandBase {

  protected static $defaultName = 'push:artifact';

  /**
   * @var array
   *
   * Composer vendor directories.
   */
  protected $vendorDirs;

  /**
   * @var array
   *
   * Composer scaffold files.
   */
  protected $scaffoldFiles;

  /**
   * @var string
   */
  private $composerJsonPath;

  /**
   * @var string
   */
  private $drupalCorePath;

  /**
   * @var string
   */
  private $docrootPath;

  /**
   * @var \AcquiaCloudApi\Response\EnvironmentResponse
   */
  private $environment;

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Build and push a code artifact to a Cloud Platform environment')
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be pushed')
      ->addOption('no-sanitize', NULL, InputOption::VALUE_NONE, 'Do not sanitize the build artifact')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Do not push changes to Acquia Cloud')
      ->addOption('destination-git-urls', 'dest-git-urls', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The URL(s) of your git repository to which the artifact branch will be pushed')
      ->addOption('destination-git-branch', 'dest-git-branch', InputOption::VALUE_REQUIRED, 'The destination branch to push the artifact to')
      ->acceptEnvironmentId()
      ->setHelp('This command builds a sanitized deploy artifact by running <options=bold>composer install</>, removing sensitive files, and committing vendor directories.' . PHP_EOL . PHP_EOL
      . 'Vendor directories and scaffold files are committed to the build artifact even if they are ignored in the source repository.' . PHP_EOL . PHP_EOL
      . 'To run additional build or sanitization steps (e.g. <options=bold>npm install</>), add a <options=bold>post-install-cmd</> script to your <options=bold>composer.json</> file: https://getcomposer.org/doc/articles/scripts.md#command-events')
      ->addUsage('--no-sanitize --dry-run # skip sanitization and Git push')
      ->addUsage('--dest-git-url=example@svn-1.prod.hosting.acquia.com:example.git --dest-branch=main-build');
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
    $artifact_dir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $this->composerJsonPath = Path::join($this->dir, 'composer.json');
    $this->docrootPath = Path::join($this->dir, 'docroot');
    $this->drupalCorePath = Path::join($this->docrootPath, 'core');
    $this->validateSourceCode();

    $is_dirty = $this->isLocalGitRepoDirty();
    $commit_hash = $this->getLocalGitCommitHash();
    if ($is_dirty) {
      throw new AcquiaCliException('Pushing code was aborted because your local Git repository has uncommitted changes. Please either commit, reset, or stash your changes via git.');
    }
    $this->checklist = new Checklist($output);
    $destination_git_urls = $this->determineDestinationGitUrls();
    $destination_git_branch = $this->determineDestinationGitBranch();

    $destination_git_urls_string = implode(',', $destination_git_urls);
    $this->io->info("The contents of $this->dir will be compiled into an artifact and pushed to the $destination_git_branch branch on the $destination_git_urls_string git remote(s)");

    $output_callback = $this->getOutputCallback($output, $this->checklist);

    $this->checklist->addItem('Preparing artifact directory');
    // @todo Output which git url we will pull from.
    $this->cloneDestinationBranch($output_callback, $artifact_dir, $destination_git_urls[0], $destination_git_branch);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Generating build artifact');
    $this->buildArtifact($output_callback, $artifact_dir);
    $this->checklist->completePreviousItem();

    if (!$input->getOption('no-sanitize')) {
      $this->checklist->addItem('Sanitizing build artifact');
      $this->sanitizeArtifact($output_callback, $artifact_dir);
      $this->checklist->completePreviousItem();
    }

    $this->checklist->addItem("Committing changes (commit hash: $commit_hash)");
    $this->commit($output_callback, $artifact_dir, $commit_hash);
    $this->checklist->completePreviousItem();

    if (!$input->getOption('dry-run')) {
      $this->checklist->addItem("Pushing changes to <options=bold>{$destination_git_branch}</> branch.");
      $this->pushArtifact($output_callback, $artifact_dir, $destination_git_urls, $destination_git_branch);
      $this->checklist->completePreviousItem();
    }
    else {
      $this->logger->warning("The <options=bold>--dry-run</> option prevented changes from being pushed to Acquia Cloud. The artifact has been built at <options=bold>$artifact_dir</>");
    }

    return 0;
  }

  /**
   * @return false|mixed|null
   */
  protected function determineDestinationGitUrls() {
    if ($this->input->getOption('destination-git-urls')) {
      return $this->input->getOption('destination-git-urls');
    }
    if ($env_var = getenv('ACLI_PUSH_ARTIFACT_DESTINATION_GIT_URLS')) {
      return $env_var;
    }
    if ($this->datastoreAcli->get('push.artifact.destination-git-urls')) {
      return $this->datastoreAcli->get('push.artifact.destination-git-urls');
    }

    $this->environment = $this->determineEnvironment($this->input, $this->output, FALSE);
    $destination_git_url = $this->environment->vcs->url;
    return [$destination_git_url];
  }

  /**
   * Prepare a directory to build the artifact.
   *
   * @param \Closure $output_callback
   * @param string $artifact_dir
   * @param string $vcs_url
   * @param string $vcs_path
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function cloneDestinationBranch(Closure $output_callback, string $artifact_dir, string $vcs_url, string $vcs_path): void {
    $fs = $this->localMachineHelper->getFilesystem();

    $output_callback('out', "Removing $artifact_dir if it exists");
    $fs->remove($artifact_dir);

    $output_callback('out', "Initializing Git in $artifact_dir");
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $process = $this->localMachineHelper->execute(['git', 'clone', '--depth=1', $vcs_url, $artifact_dir], $output_callback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Failed to clone repository from the Cloud Platform: {message}', ['message' => $process->getErrorOutput()]);
    }
    $process = $this->localMachineHelper->execute(['git', 'fetch', '--depth=1', $vcs_url, $vcs_path . ':' . $vcs_path], $output_callback, $artifact_dir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      // Remote branch does not exist. Just create it locally. This will create
      // the new branch off of the current commit.
      $process = $this->localMachineHelper->execute(['git', 'checkout', '-b', $vcs_path], $output_callback, $artifact_dir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
      if (!$process->isSuccessful()) {
        throw new AcquiaCliException("Could not checkout $vcs_path branch locally: {message}", ['message' => $process->getErrorOutput() . $process->getOutput()]);
      }
    }
    else {
      $process = $this->localMachineHelper->execute(['git', 'checkout', $vcs_path], $output_callback, $artifact_dir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
      if (!$process->isSuccessful()) {
        throw new AcquiaCliException("Could not checkout $vcs_path branch locally: {message}", ['message' => $process->getErrorOutput() . $process->getOutput()]);
      }
    }

    $output_callback('out', 'Global .gitignore file is temporarily disabled during artifact builds.');
    $this->localMachineHelper->execute(['git', 'config', '--local', 'core.excludesFile', 'false'], $output_callback, $artifact_dir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    $this->localMachineHelper->execute(['git', 'config', '--local', 'core.fileMode', 'true'], $output_callback, $artifact_dir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));

    // Vendor directories can be "corrupt" (i.e. missing scaffold files due to earlier sanitization) in ways that break composer install.
    $output_callback('out', 'Removing vendor directories');
    foreach ($this->vendorDirs($artifact_dir) as $vendor_directory) {
      $fs->remove(Path::join($artifact_dir, $vendor_directory));
    }
  }

  /**
   * Build the artifact.
   *
   * @param \Closure $output_callback
   * @param string $artifact_dir
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function buildArtifact(Closure $output_callback, string $artifact_dir): void {
    // @todo generate a deploy identifier
    // @see https://git.drupalcode.org/project/drupal/-/blob/9.1.x/sites/default/default.settings.php#L295
    $output_callback('out', "Mirroring source files from {$this->dir} to $artifact_dir");
    $originFinder = $this->localMachineHelper->getFinder();
    $originFinder->files()->in($this->dir)
      // Include dot files like .htaccess.
      ->ignoreDotFiles(FALSE)
      // Ignore VCS ignored files (e.g. vendor) to speed up the mirror (Composer will restore them later).
      ->ignoreVCSIgnored(TRUE);
    $targetFinder = $this->localMachineHelper->getFinder();
    $targetFinder->files()->in($artifact_dir)->ignoreDotFiles(FALSE);
    $this->localMachineHelper->getFilesystem()->mirror($this->dir, $artifact_dir, $originFinder, ['override' => TRUE, 'delete' => TRUE], $targetFinder);

    $this->localMachineHelper->checkRequiredBinariesExist(['composer']);
    $output_callback('out', 'Installing Composer production dependencies');
    $process = $this->localMachineHelper->execute(['composer', 'install', '--no-dev', '--no-interaction', '--optimize-autoloader'], $output_callback, $artifact_dir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Unable to install composer dependencies: {message}", ['message' => $process->getOutput() . $process->getErrorOutput()]);
    }
  }

  /**
   * Sanitize the artifact.
   *
   * @param \Closure $output_callback
   * @param string $artifact_dir
   */
  protected function sanitizeArtifact(Closure $output_callback, string $artifact_dir):void {
    $output_callback('out', 'Finding Drupal core text files');
    $sanitizeFinder = $this->localMachineHelper->getFinder()
      ->files()
      ->name('*.txt')
      ->notName('LICENSE.txt')
      ->in("$artifact_dir/docroot/core");

    $output_callback('out', 'Finding VCS directories');
    $vcsFinder = $this->localMachineHelper->getFinder()
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
    $dbInstallFinder = $this->localMachineHelper->getFinder()
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
    $textFileFinder = $this->localMachineHelper->getFinder()
      ->files()
      ->in(["$artifact_dir/docroot"])
      ->name('/(' . implode('|', $filenames) . ')\.(md|txt)$/');
    if ($textFileFinder->hasResults()) {
      $sanitizeFinder->append($textFileFinder);
    }

    $output_callback('out', "Removing sensitive files from build");
    $this->localMachineHelper->getFilesystem()->remove($sanitizeFinder);
  }

  /**
   * Commit the artifact.
   *
   * @param \Closure $output_callback
   * @param string $artifact_dir
   * @param string $commit_hash
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function commit(Closure $output_callback, string $artifact_dir, string $commit_hash):void {
    $output_callback('out', 'Adding and committing changed files');
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $process = $this->localMachineHelper->execute(['git', 'add', '-A'], $output_callback, $artifact_dir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Could not add files to artifact via git: {message}", ['message' => $process->getErrorOutput() . $process->getOutput()]);
    }
    foreach (array_merge($this->vendorDirs($artifact_dir), $this->scaffoldFiles($artifact_dir)) as $file) {
      $this->logger->debug("Forcibly adding $file");
      $this->localMachineHelper->execute(['git', 'add', '-f', $file], NULL, $artifact_dir, FALSE);
      if (!$process->isSuccessful()) {
        // This will fatally error if the file doesn't exist. Suppress error output.
        $this->io->warning("Unable to forcibly add $file to new branch");
      }
    }
    $commit_message = $this->generateCommitMessage($commit_hash);
    $this->localMachineHelper->execute(['git', 'commit', '-m', $commit_message], $output_callback, $artifact_dir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Could not commit via git: {message}", ['message' => $process->getErrorOutput() . $process->getOutput()]);
    }
  }

  /**
   * @param string $commit_hash
   *
   * @return array|false|string
   */
  public function generateCommitMessage(string $commit_hash) {
    if ($env_var = getenv('ACLI_PUSH_ARTIFACT_COMMIT_MSG')) {
      return $env_var;
    }

    return "Automated commit by Acquia CLI (source commit: $commit_hash)";
  }

  /**
   * Push the artifact.
   *
   * @param \Closure $output_callback
   * @param string $artifact_dir
   * @param array $vcs_urls
   * @param string $dest_git_branch
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function pushArtifact(Closure $output_callback, string $artifact_dir, array $vcs_urls, string $dest_git_branch):void {
    foreach ($vcs_urls as $vcs_url) {
      $output_callback('out', "Pushing changes to Acquia Git ($vcs_url)");
      $this->localMachineHelper->checkRequiredBinariesExist(['git']);
      $args = [
        'git',
        'push',
        $vcs_url,
        $dest_git_branch . ':' . $dest_git_branch
      ];
      $process = $this->localMachineHelper->execute($args, $output_callback, $artifact_dir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
      if (!$process->isSuccessful()) {
        throw new AcquiaCliException("Unable to push artifact: {message}", ['message' => $process->getOutput() . $process->getErrorOutput()]);
      }
    }
  }

  /**
   * Get a list of Composer vendor directories from the root composer.json.
   *
   * @param string $artifact_dir
   *
   * @return array|string[]
   */
  protected function vendorDirs(string $artifact_dir): array {
    if (!empty($this->vendorDirs)) {
      return $this->vendorDirs;
    }

    $this->vendorDirs = [
      'vendor',
    ];
    if (file_exists($this->composerJsonPath)) {
      $composer_json = json_decode($this->localMachineHelper->readFile($this->composerJsonPath), TRUE);

      foreach ($composer_json['extra']['installer-paths'] as $path => $type) {
        $this->vendorDirs[] = str_replace('/{$name}', '', $path);
      }
      return $this->vendorDirs;
    }
    return [];
  }

  /**
   * Get a list of scaffold files from Drupal core's composer.json.
   *
   * @param string $artifact_dir
   *
   * @return array
   */
  protected function scaffoldFiles(string $artifact_dir): array {
    if (!empty($this->scaffoldFiles)) {
      return $this->scaffoldFiles;
    }

    $this->scaffoldFiles = [];
    $composer_json = json_decode($this->localMachineHelper->readFile(Path::join($artifact_dir, 'docroot', 'core', 'composer.json')), TRUE);
    foreach ($composer_json['extra']['drupal-scaffold']['file-mapping'] as $file => $asset_path) {
      if (strpos($file, '[web-root]') === 0) {
        $this->scaffoldFiles[] = str_replace('[web-root]', 'docroot/core', $file);
      }
    }
    $this->scaffoldFiles[] = 'docroot/autoload.php';

    return $this->scaffoldFiles;
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validateSourceCode(): void {
    $required_paths = [
      $this->composerJsonPath,
      $this->docrootPath
    ];
    foreach ($required_paths as $required_path) {
      if (!file_exists($required_path)) {
        throw new AcquiaCliException("Your current directory does not look like a valid Drupal application. $required_path is missing.");
      }
    }
  }

  /**
   * @return mixed
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function determineDestinationGitBranch() {
    if ($this->input->getOption('destination-git-branch')) {
      return $this->input->getOption('destination-git-branch');
    }
    if ($env_var = getenv('ACLI_PUSH_ARTIFACT_DESTINATION_GIT_BRANCH')) {
      return $env_var;
    }
    if (strpos($this->environment->vcs->path, 'tags') === 0) {
      throw new AcquiaCliException("You cannot push to an environment that has a git tag deployed to it. Environment {$this->environment->name} has {$this->environment->vcs->path} deployed. Please select a different environment.");
    }
    return $this->environment->vcs->path;
  }

}
