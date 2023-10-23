<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

class PushArtifactCommand extends PullCommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'push:artifact';

  /**
   * Composer vendor directories.
   *
   * @var array<mixed>
   */
  protected array $vendorDirs;

  /**
   * Composer scaffold files.
   *
   * @var array<mixed>
   */
  protected array $scaffoldFiles;

  private string $composerJsonPath;

  private string $docrootPath;

  private string $destinationGitRef;

  protected function configure(): void {
    $this->setDescription('Build and push a code artifact to a Cloud Platform environment')
      ->addOption('dir', NULL, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be pushed')
      ->addOption('no-sanitize', NULL, InputOption::VALUE_NONE, 'Do not sanitize the build artifact')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Deprecated: Use no-push instead')
      ->addOption('no-push', NULL, InputOption::VALUE_NONE, 'Do not push changes to Acquia Cloud')
      ->addOption('no-commit', NULL, InputOption::VALUE_NONE, 'Do not commit changes. Implies no-push')
      ->addOption('no-clone', NULL, InputOption::VALUE_NONE, 'Do not clone repository. Implies no-commit and no-push')
      ->addOption('destination-git-urls', 'u', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'The URL(s) of your git repository to which the artifact branch will be pushed')
      ->addOption('destination-git-branch', 'b', InputOption::VALUE_REQUIRED, 'The destination branch to push the artifact to')
      ->addOption('destination-git-tag', 't', InputOption::VALUE_REQUIRED, 'The destination tag to push the artifact to. Using this option requires also using the --source-git-tag option')
      ->addOption('source-git-tag', 's', InputOption::VALUE_REQUIRED, 'The source tag from which to create the tag artifact')
      ->acceptEnvironmentId()
      ->setHelp('This command builds a sanitized deploy artifact by running <options=bold>composer install</>, removing sensitive files, and committing vendor directories.' . PHP_EOL . PHP_EOL
      . 'Vendor directories and scaffold files are committed to the build artifact even if they are ignored in the source repository.' . PHP_EOL . PHP_EOL
      . 'To run additional build or sanitization steps (e.g. <options=bold>npm install</>), add a <options=bold>post-install-cmd</> script to your <options=bold>composer.json</> file: https://getcomposer.org/doc/articles/scripts.md#command-events')
      ->addUsage('--destination-git-branch=main-build')
      ->addUsage('--source-git-tag=foo-build --destination-git-tag=1.0.0')
      ->addUsage('--destination-git-urls=example@svn-1.prod.hosting.acquia.com:example.git --destination-git-branch=main-build');
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->setDirAndRequireProjectCwd($input);
    if ($input->getOption('no-clone')) {
      $input->setOption('no-commit', TRUE);
      $input->setOption('no-push', TRUE);
    }
    if ($input->getOption('no-commit')) {
      $input->setOption('no-push', TRUE);
    }
    $artifactDir = Path::join(sys_get_temp_dir(), 'acli-push-artifact');
    $this->composerJsonPath = Path::join($this->dir, 'composer.json');
    $this->docrootPath = Path::join($this->dir, 'docroot');
    $this->validateSourceCode();

    $isDirty = $this->isLocalGitRepoDirty();
    $commitHash = $this->getLocalGitCommitHash();
    if ($isDirty) {
      throw new AcquiaCliException('Pushing code was aborted because your local Git repository has uncommitted changes. Either commit, reset, or stash your changes via git.');
    }
    $this->checklist = new Checklist($output);
    $outputCallback = $this->getOutputCallback($output, $this->checklist);

    $destinationGitUrls = [];
    $destinationGitRef = '';
    if (!$input->getOption('no-clone')) {
      $applicationUuid = $this->determineCloudApplication();
      $destinationGitUrls = $this->determineDestinationGitUrls($applicationUuid);
      $destinationGitRef = $this->determineDestinationGitRef();
      $sourceGitBranch = $this->determineSourceGitRef();
      $destinationGitUrlsString = implode(',', $destinationGitUrls);
      $refType = $this->input->getOption('destination-git-tag') ? 'tag' : 'branch';
      $this->io->note([
        "Acquia CLI will:",
        "- git clone $sourceGitBranch from $destinationGitUrls[0]",
        "- Compile the contents of $this->dir into an artifact in a temporary directory",
        "- Copy the artifact files into the checked out copy of $sourceGitBranch",
        "- Commit changes and push the $destinationGitRef $refType to the following git remote(s):",
        "  $destinationGitUrlsString",
      ]);

      $this->checklist->addItem('Preparing artifact directory');
      $this->cloneSourceBranch($outputCallback, $artifactDir, $destinationGitUrls[0], $sourceGitBranch);
      $this->checklist->completePreviousItem();
    }

    $this->checklist->addItem('Generating build artifact');
    $this->buildArtifact($outputCallback, $artifactDir);
    $this->checklist->completePreviousItem();

    if (!$input->getOption('no-sanitize')) {
      $this->checklist->addItem('Sanitizing build artifact');
      $this->sanitizeArtifact($outputCallback, $artifactDir);
      $this->checklist->completePreviousItem();
    }

    if (!$input->getOption('no-commit')) {
      $this->checklist->addItem("Committing changes (commit hash: $commitHash)");
      $this->commit($outputCallback, $artifactDir, $commitHash);
      $this->checklist->completePreviousItem();
    }

    if (!$input->getOption('dry-run') && !$input->getOption('no-push')) {
      if ($tagName = $input->getOption('destination-git-tag')) {
        $this->checklist->addItem("Creating <options=bold>$tagName</> tag.");
        $this->createTag($tagName, $outputCallback, $artifactDir);
        $this->checklist->completePreviousItem();
        $this->checklist->addItem("Pushing changes to <options=bold>$tagName</> tag.");
        $this->pushArtifact($outputCallback, $artifactDir, $destinationGitUrls, $tagName);
      }
      else {
        $this->checklist->addItem("Pushing changes to <options=bold>$destinationGitRef</> branch.");
        $this->pushArtifact($outputCallback, $artifactDir, $destinationGitUrls, $destinationGitRef . ':' . $destinationGitRef);
      }
      $this->checklist->completePreviousItem();
    }
    else {
      $this->logger->warning("The <options=bold>--dry-run</> (deprecated) or <options=bold>--no-push</> option prevented changes from being pushed to Acquia Cloud. The artifact has been built at <options=bold>$artifactDir</>");
    }

    return Command::SUCCESS;
  }

  /**
   * @return string[]
   */
  private function determineDestinationGitUrls(?string $applicationUuid): array {
    if ($this->input->getOption('destination-git-urls')) {
      return $this->input->getOption('destination-git-urls');
    }
    if ($envVar = getenv('ACLI_PUSH_ARTIFACT_DESTINATION_GIT_URLS')) {
      return explode(',', $envVar);
    }
    if ($this->datastoreAcli->get('push.artifact.destination-git-urls')) {
      return $this->datastoreAcli->get('push.artifact.destination-git-urls');
    }

    return [$this->getAnyVcsUrl($applicationUuid)];
  }

  /**
   * Prepare a directory to build the artifact.
   */
  private function cloneSourceBranch(Closure $outputCallback, string $artifactDir, string $vcsUrl, string $vcsPath): void {
    $fs = $this->localMachineHelper->getFilesystem();

    $outputCallback('out', "Removing $artifactDir if it exists");
    $fs->remove($artifactDir);

    $outputCallback('out', "Initializing Git in $artifactDir");
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $process = $this->localMachineHelper->execute(['git', 'clone', '--depth=1', $vcsUrl, $artifactDir], $outputCallback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Failed to clone repository from the Cloud Platform: {message}', ['message' => $process->getErrorOutput()]);
    }
    $process = $this->localMachineHelper->execute(['git', 'fetch', '--depth=1', $vcsUrl, $vcsPath . ':' . $vcsPath], $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      // Remote branch does not exist. Just create it locally. This will create
      // the new branch off of the current commit.
      $process = $this->localMachineHelper->execute(['git', 'checkout', '-b', $vcsPath], $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    }
    else {
      $process = $this->localMachineHelper->execute(['git', 'checkout', $vcsPath], $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    }
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Could not checkout $vcsPath branch locally: {message}", ['message' => $process->getErrorOutput() . $process->getOutput()]);
    }

    $outputCallback('out', 'Global .gitignore file is temporarily disabled during artifact builds.');
    $this->localMachineHelper->execute(['git', 'config', '--local', 'core.excludesFile', 'false'], $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    $this->localMachineHelper->execute(['git', 'config', '--local', 'core.fileMode', 'true'], $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));

    // Vendor directories can be "corrupt" (i.e. missing scaffold files due to earlier sanitization) in ways that break composer install.
    $outputCallback('out', 'Removing vendor directories');
    foreach ($this->vendorDirs() as $vendorDirectory) {
      $fs->remove(Path::join($artifactDir, $vendorDirectory));
    }
  }

  /**
   * Build the artifact.
   */
  private function buildArtifact(Closure $outputCallback, string $artifactDir): void {
    // @todo generate a deploy identifier
    // @see https://git.drupalcode.org/project/drupal/-/blob/9.1.x/sites/default/default.settings.php#L295
    $outputCallback('out', "Mirroring source files from $this->dir to $artifactDir");
    $originFinder = $this->localMachineHelper->getFinder();
    $originFinder->in($this->dir)
      // Include dot files like .htaccess.
      ->ignoreDotFiles(FALSE)
      // Ignore VCS ignored files (e.g. vendor) to speed up the mirror (Composer will restore them later).
      ->ignoreVCSIgnored(TRUE);
    $targetFinder = $this->localMachineHelper->getFinder();
    $targetFinder->in($artifactDir)->ignoreDotFiles(FALSE);
    $this->localMachineHelper->getFilesystem()->mirror($this->dir, $artifactDir, $originFinder, ['override' => TRUE, 'delete' => TRUE], $targetFinder);

    $this->localMachineHelper->checkRequiredBinariesExist(['composer']);
    $outputCallback('out', 'Installing Composer production dependencies');
    $process = $this->localMachineHelper->execute(['composer', 'install', '--no-dev', '--no-interaction', '--optimize-autoloader'], $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Unable to install composer dependencies: {message}", ['message' => $process->getOutput() . $process->getErrorOutput()]);
    }
  }

  /**
   * Sanitize the artifact.
   */
  private function sanitizeArtifact(Closure $outputCallback, string $artifactDir): void {
    $outputCallback('out', 'Finding Drupal core text files');
    $sanitizeFinder = $this->localMachineHelper->getFinder()
      ->files()
      ->name('*.txt')
      ->notName('LICENSE.txt')
      ->in("$artifactDir/docroot/core");

    $outputCallback('out', 'Finding VCS directories');
    $vcsFinder = $this->localMachineHelper->getFinder()
      ->ignoreDotFiles(FALSE)
      ->ignoreVCS(FALSE)
      ->directories()
      ->in(["$artifactDir/docroot",
        "$artifactDir/vendor",
      ])
      ->name('.git');
    $drushDir = "$artifactDir/drush";
    if (file_exists($drushDir)) {
      $vcsFinder->in($drushDir);
    }
    if ($vcsFinder->hasResults()) {
      $sanitizeFinder->append($vcsFinder);
    }

    $outputCallback('out', 'Finding INSTALL database text files');
    $dbInstallFinder = $this->localMachineHelper->getFinder()
      ->files()
      ->in([$artifactDir])
      ->name('/INSTALL\.[a-z]+\.(md|txt)$/');
    if ($dbInstallFinder->hasResults()) {
      $sanitizeFinder->append($dbInstallFinder);
    }

    $outputCallback('out', 'Finding other common text files');
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
      ->in(["$artifactDir/docroot"])
      ->name('/(' . implode('|', $filenames) . ')\.(md|txt)$/');
    if ($textFileFinder->hasResults()) {
      $sanitizeFinder->append($textFileFinder);
    }

    $outputCallback('out', "Removing sensitive files from build");
    $this->localMachineHelper->getFilesystem()->remove($sanitizeFinder);
  }

  /**
   * Commit the artifact.
   */
  private function commit(Closure $outputCallback, string $artifactDir, string $commitHash): void {
    $outputCallback('out', 'Adding and committing changed files');
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $process = $this->localMachineHelper->execute(['git', 'add', '-A'], $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Could not add files to artifact via git: {message}", ['message' => $process->getErrorOutput() . $process->getOutput()]);
    }
    foreach (array_merge($this->vendorDirs(), $this->scaffoldFiles($artifactDir)) as $file) {
      $this->logger->debug("Forcibly adding $file");
      $this->localMachineHelper->execute(['git', 'add', '-f', $file], NULL, $artifactDir, FALSE);
      if (!$process->isSuccessful()) {
        // This will fatally error if the file doesn't exist. Suppress error output.
        $this->io->warning("Unable to forcibly add $file to new branch");
      }
    }
    $commitMessage = $this->generateCommitMessage($commitHash);
    $process = $this->localMachineHelper->execute(['git', 'commit', '-m', $commitMessage], $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Could not commit via git: {message}", ['message' => $process->getErrorOutput() . $process->getOutput()]);
    }
  }

  private function generateCommitMessage(string $commitHash): array|string {
    if ($envVar = getenv('ACLI_PUSH_ARTIFACT_COMMIT_MSG')) {
      return $envVar;
    }

    return "Automated commit by Acquia CLI (source commit: $commitHash)";
  }

  /**
   * Push the artifact.
   */
  private function pushArtifact(Closure $outputCallback, string $artifactDir, array $vcsUrls, string $destGitBranch): void {
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    foreach ($vcsUrls as $vcsUrl) {
      $outputCallback('out', "Pushing changes to Acquia Git ($vcsUrl)");
      $args = [
        'git',
        'push',
        $vcsUrl,
        $destGitBranch,
      ];
      $process = $this->localMachineHelper->execute($args, $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
      if (!$process->isSuccessful()) {
        throw new AcquiaCliException("Unable to push artifact: {message}", ['message' => $process->getOutput() . $process->getErrorOutput()]);
      }
    }
  }

  /**
   * Get a list of Composer vendor directories from the root composer.json.
   *
   * @return array|string[]
   */
  private function vendorDirs(): array {
    if (!empty($this->vendorDirs)) {
      return $this->vendorDirs;
    }

    $this->vendorDirs = [
      'vendor',
    ];
    if (file_exists($this->composerJsonPath)) {
      $composerJson = json_decode($this->localMachineHelper->readFile($this->composerJsonPath), TRUE, 512, JSON_THROW_ON_ERROR);

      foreach ($composerJson['extra']['installer-paths'] as $path => $type) {
        $this->vendorDirs[] = str_replace('/{$name}', '', $path);
      }
      return $this->vendorDirs;
    }
    return [];
  }

  /**
   * Get a list of scaffold files from Drupal core's composer.json.
   *
   * @return array<mixed>
   */
  private function scaffoldFiles(string $artifactDir): array {
    if (!empty($this->scaffoldFiles)) {
      return $this->scaffoldFiles;
    }

    $this->scaffoldFiles = [];
    $composerJson = json_decode($this->localMachineHelper->readFile(Path::join($artifactDir, 'docroot', 'core', 'composer.json')), TRUE, 512, JSON_THROW_ON_ERROR);
    foreach ($composerJson['extra']['drupal-scaffold']['file-mapping'] as $file => $assetPath) {
      if (str_starts_with($file, '[web-root]')) {
        $this->scaffoldFiles[] = str_replace('[web-root]', 'docroot', $file);
      }
    }
    $this->scaffoldFiles[] = 'docroot/autoload.php';

    return $this->scaffoldFiles;
  }

  private function validateSourceCode(): void {
    $requiredPaths = [
      $this->composerJsonPath,
      $this->docrootPath,
    ];
    foreach ($requiredPaths as $requiredPath) {
      if (!file_exists($requiredPath)) {
        throw new AcquiaCliException("Your current directory does not look like a valid Drupal application. $requiredPath is missing.");
      }
    }
  }

  private function determineSourceGitRef(): string {
    if ($this->input->getOption('source-git-tag')) {
      return $this->input->getOption('source-git-tag');
    }
    if ($envVar = getenv('ACLI_PUSH_ARTIFACT_SOURCE_GIT_TAG')) {
      return $envVar;
    }
    if ($this->input->getOption('destination-git-tag')) {
      throw new AcquiaCliException('You must also set the --source-git-tag option when setting the --destination-git-tag option.');
    }

    // Assume the source and destination branches are the same.
    return $this->destinationGitRef;
  }

  private function determineDestinationGitRef(): string {
    if ($this->input->getOption('destination-git-tag')) {
      $this->destinationGitRef = $this->input->getOption('destination-git-tag');
      return $this->destinationGitRef;
    }
    if ($envVar = getenv('ACLI_PUSH_ARTIFACT_DESTINATION_GIT_TAG')) {
      $this->destinationGitRef = $envVar;
      return $this->destinationGitRef;
    }
    if ($this->input->getOption('destination-git-branch')) {
      $this->destinationGitRef = $this->input->getOption('destination-git-branch');
      return $this->destinationGitRef;
    }
    if ($envVar = getenv('ACLI_PUSH_ARTIFACT_DESTINATION_GIT_BRANCH')) {
      $this->destinationGitRef = $envVar;
      return $this->destinationGitRef;
    }

    $environment = $this->determineEnvironment($this->input, $this->output);
    if (str_starts_with($environment->vcs->path, 'tags')) {
      throw new AcquiaCliException("You cannot push to an environment that has a git tag deployed to it. Environment $environment->name has {$environment->vcs->path} deployed. Select a different environment.");
    }

    $this->destinationGitRef = $environment->vcs->path;

    return $this->destinationGitRef;
  }

  private function createTag(mixed $tagName, Closure $outputCallback, string $artifactDir): void {
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $process = $this->localMachineHelper->execute([
      'git',
      'tag',
      $tagName,
    ], $outputCallback, $artifactDir, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Failed to create Git tag: {message}', ['message' => $process->getErrorOutput()]);
    }
  }

}
