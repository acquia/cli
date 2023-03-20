<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use loophp\phposinfo\OsInfo;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use function Safe\file_get_contents;

/**
 * Class LocalMachineHelper.
 *
 * A helper for executing commands on the local client. A wrapper for 'exec' and 'passthru'.
 *
 * @package Acquia\Cli\Helpers
 */
class LocalMachineHelper {
  use LoggerAwareTrait;

  private OutputInterface $output;
  private InputInterface $input;
  private $isTty;
  private array $installedBinaries = [];

  private SymfonyStyle $io;

  public function __construct(
      InputInterface $input,
      OutputInterface $output,
      LoggerInterface $logger
  ) {
    $this->input = $input;
    $this->output = $output;
    $this->setLogger($logger);
    $this->io = new SymfonyStyle($input, $output);
  }

  /**
   * Check if a command exists.
   *
   * This won't find aliases or shell built-ins, so use it mindfully (e.g. only
   * for commands that you _know_ to be system commands).
   *
   * @param $command
   */
  public function commandExists($command): bool {
    if (array_key_exists($command, $this->installedBinaries)) {
      return (bool) $this->installedBinaries[$command];
    }
    $os_command = OsInfo::isWindows() ? ['where', $command] : ['which', $command];
    // phpcs:ignore
    $exists = $this->execute($os_command, NULL, NULL, FALSE)->isSuccessful();
    $this->installedBinaries[$command] = $exists;
    return $exists;
  }

  /**
   * @param string[] $binaries
   * @throws AcquiaCliException
   */
  public function checkRequiredBinariesExist(array $binaries = []): void {
    foreach ($binaries as $binary) {
      if (!$this->commandExists($binary)) {
        throw new AcquiaCliException("The required binary `$binary` does not exist. Install it and ensure it exists in a location listed in your system \$PATH");
      }
    }
  }

  /**
   * Executes a buffered command.
   *
   * @param array $cmd
   *   The command to execute.
   * @param null $callback
   *   A function to run while waiting for the process to complete.
   */
  public function execute(array $cmd, callable $callback = NULL, string $cwd = NULL, ?bool $print_output = TRUE, float $timeout = NULL, array $env = NULL): Process {
    $process = new Process($cmd);
    $process = $this->configureProcess($process, $cwd, $print_output, $timeout, $env);
    return $this->executeProcess($process, $callback, $print_output);
  }

  /**
   * Executes a command directly in a shell (without additional parsing).
   *
   * Use `execute()` instead whenever possible. `executeFromCmd()` does not
   * automatically escape arguments and should only be used for commands with
   * pipes or redirects not supported by `execute()`.
   *
   * Windows does not support prepending commands with environment variables.
   *
   * @param callable|null $callback
   * @param string|null $cwd
   * @param int|null $timeout
   * @param array|null $env
   */
  public function executeFromCmd(string $cmd, callable $callback = NULL, string $cwd = NULL, ?bool $print_output = TRUE, int $timeout = NULL, array $env = NULL): Process {
    $process = Process::fromShellCommandline($cmd);
    $process = $this->configureProcess($process, $cwd, $print_output, $timeout, $env);

    return $this->executeProcess($process, $callback, $print_output);
  }

  /**
   * @param string|null $cwd
   * @param array|null $env
   */
  private function configureProcess(Process $process, string $cwd = NULL, ?bool $print_output = TRUE, float $timeout = NULL, array $env = NULL): Process {
    if (function_exists('posix_isatty') && !posix_isatty(STDIN)) {
      $process->setInput(STDIN);
    }
    if ($cwd) {
      $process->setWorkingDirectory($cwd);
    }
    if ($print_output) {
      $process->setTty($this->useTty());
    }
    if ($env) {
      $process->setEnv($env);
    }
    $process->setTimeout($timeout);

    return $process;
  }

  /**
   * @param callable|null $callback
   */
  private function executeProcess(Process $process, callable $callback = NULL, ?bool $print_output = TRUE): Process {
    if ($callback === NULL && $print_output !== FALSE) {
      $callback = function ($type, $buffer): void {
        $this->output->write($buffer);
      };
    }
    $process->start();
    $process->wait($callback);

    $this->logger->notice('Command: {command} [Exit: {exit}]', [
      'command' => $process->getCommandLine(),
      'exit' => $process->getExitCode(),
    ]);

    return $process;
  }

  /**
   * Returns a set-up filesystem object.
   */
  public function getFilesystem(): Filesystem {
    return new Filesystem();
  }

  /**
   * Returns a finder object.
   */
  public function getFinder(): Finder {
    return new Finder();
  }

  /**
   * Reads to a file from the local system.
   *
   * @param string $filename
   *   Name of the file to read.
   * @return string Content read from that file
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Safe\Exceptions\FilesystemException
   */
  public function readFile(string $filename): string {
    return file_get_contents($this->getLocalFilepath($filename));
  }

  /**
   * @param $filepath
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function getLocalFilepath($filepath): string {
    return $this->fixFilename($filepath);
  }

  /**
   * Determine whether the use of a tty is appropriate.
   */
  public function useTty(): bool {
    if (isset($this->isTty)) {
      return $this->isTty;
    }

    // If we are not in interactive mode, then never use a tty.
    if (!$this->input->isInteractive()) {
      return FALSE;
    }

    // If we are in interactive mode (or at least the user did not
    // specify -n / --no-interaction), then also prevent the use
    // of a tty if stdout is redirected.
    // Otherwise, let the local machine helper decide whether to use a tty.
    if (function_exists('posix_isatty')) {
      return (posix_isatty(STDOUT) && posix_isatty(STDIN));
    }

    return FALSE;
  }

  public function setIsTty(?bool $isTty): void {
    $this->isTty = $isTty;
  }

  /**
   * Writes to a file on the local system.
   *
   * @param string $filename
   *   Name of the file to write to.
   * @param string $content
   *   Content to write to the file.
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function writeFile(string $filename, string $content): void {
    $this->getFilesystem()->dumpFile($this->getLocalFilepath($filename), $content);
  }

  /**
   * Accepts a filename/full path and localizes it to the user's system.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function fixFilename(string $filename): string {
    // '~' is only an alias for $HOME if it's at the start of the path.
    // On Windows, '~' (not as an alias) can appear other places in the path.
    return preg_replace('/^~/', self::getHomeDir(), $filename);
  }

  /**
   * Returns the appropriate home directory.
   *
   * Adapted from Ads Package Manager by Ed Reel.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @author Ed Reel <@uberhacker>
   * @url https://github.com/uberhacker/tpm
   */
  public static function getHomeDir(): string {
    $home = getenv('HOME');
    if (!$home) {
      $system = '';
      if (getenv('MSYSTEM') !== NULL) {
        $system = strtoupper(substr(getenv('MSYSTEM'), 0, 4));
      }
      if ($system !== 'MING') {
        $home = getenv('HOMEPATH');
      }
    }

    if (!$home) {
      throw new AcquiaCliException('Could not determine $HOME directory. Ensure $HOME is set in your shell.');
    }

    return $home;
  }

  /**
   * Get the project root directory for the working directory.
   *
   * This method assumes you are running `acli` in a directory containing a
   * Drupal docroot either as a sibling or parent(N) of the working directory.
   *
   * Typically the root directory would also be a Git repository root, though it
   * doesn't have to be (such as for brand-new projects that haven't initialized
   * Git yet).
   *
   * @return null|string
   *   Root.
   */
  public static function getProjectDir(): ?string {
    $possible_project_roots = [
      getcwd(),
    ];
    // Check for PWD - some local environments will not have this key.
    if (getenv('PWD') && !in_array(getenv('PWD'), $possible_project_roots, TRUE)) {
      array_unshift($possible_project_roots, getenv('PWD'));
    }
    foreach ($possible_project_roots as $possible_project_root) {
      if ($project_root = self::find_directory_containing_files($possible_project_root, ['docroot'])) {
        return realpath($project_root);
      }
    }

    return NULL;
  }

  /**
   * Traverses file system upwards in search of a given file.
   *
   * Begins searching for $file in $working_directory and climbs up directories
   * $max_height times, repeating search.
   *
   * @param string $working_directory
   *   Working directory.
   * @param array $files
   *   Files.
   * @param int $max_height
   *   Max Height.
   * @return bool|string
   *   FALSE if file was not found. Otherwise, the directory path containing the
   *   file.
   */
  private static function find_directory_containing_files(string $working_directory, array $files, int $max_height = 10): bool|string {
    $file_path = $working_directory;
    for ($i = 0; $i <= $max_height; $i++) {
      if (self::files_exist($file_path, $files)) {
        return $file_path;
      }

      $file_path = dirname($file_path);
    }

    return FALSE;
  }

  /**
   * Determines if an array of files exist in a particular directory.
   *
   * @param string $dir
   *   Dir.
   * @param array $files
   *   Files.
   * @return bool
   *   Exists.
   */
  private static function files_exist(string $dir, array $files): bool {
    foreach ($files as $file) {
      if (file_exists(Path::join($dir, $file))) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Determines if a browser is available on the local machine.
   *
   * @return bool
   *   TRUE if a browser is available.
   */
  public static function isBrowserAvailable(): bool {
    if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      return FALSE;
    }
    if (getenv('DISPLAY')) {
      return TRUE;
    }
    if (OsInfo::isWindows() || OsInfo::isApple()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Starts a background browser/tab for the current site or a specified URL.
   *
   * @param $uri
   *   Optional URI or site path to open in browser. If omitted, or if a site path
   *   is specified, the current site home page uri will be prepended if the site's
   *   hostname resolves.
   * @param string|null $browser The command to run to launch a browser.
   * @return bool
   *   TRUE if browser was opened. FALSE if browser was disabled by the user or a
   *   default browser could not be found.
   */
  public function startBrowser($uri = NULL, string $browser = NULL): bool {
    // We can only open a browser if we have a DISPLAY environment variable on
    // POSIX or are running Windows or OS X.
    if (!self::isBrowserAvailable()) {
      $this->logger->info('No graphical display appears to be available, not starting browser.');
      return FALSE;
    }
    $host = parse_url($uri, PHP_URL_HOST);

    // Validate that the host part of the URL resolves, so we don't attempt to
    // open the browser for http://default or similar invalid hosts.
    $hosterror = (gethostbynamel($host) === FALSE);
    $iperror = (ip2long($host) && gethostbyaddr($host) == $host);
    if ($hosterror || $iperror) {
      $this->logger->warning(
            '!host does not appear to be a resolvable hostname or IP, not starting browser.',
            ['!host' => $host]
        );

      return FALSE;
    }
    if ($browser === NULL) {
      // See if we can find an OS helper to open URLs in default browser.
      if ($this->commandExists('xdg-open')) {
        // Linux.
        $browser = 'xdg-open';
      }
      else if ($this->commandExists('open')) {
        // Darwin.
        $browser = 'open';
      }
      else if (OsInfo::isWindows()) {
        $browser = 'start';
      }
      else {
        $this->logger->warning('Could not find a browser on your local machine. Check that one of <options=bold>xdg-open</>, <options=bold>open</>, or <options=bold>start</> are installed.');
        return FALSE;
      }
    }
    if ($browser) {
      $this->io->info("Opening $uri");
      $this->executeFromCmd("$browser $uri");

      return TRUE;
    }
    return FALSE;
  }

}
