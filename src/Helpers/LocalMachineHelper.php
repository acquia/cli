<?php

declare(strict_types=1);

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use loophp\phposinfo\OsInfo;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

use function safe\file_get_contents;

/**
 * A helper for executing commands on the local client. A wrapper for 'exec'
 * and 'passthru'.
 */
class LocalMachineHelper
{
    use LoggerAwareTrait;

    private ?bool $isTty;

    /**
     * @var array<bool>
     */
    private array $installedBinaries = [];

    private SymfonyStyle $io;

    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        LoggerInterface $logger
    ) {
        $this->setLogger($logger);
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * Check if a command exists.
     *
     * This won't find aliases or shell built-ins, so use it mindfully (e.g.
     * only for commands that you _know_ to be system commands).
     */
    public function commandExists(string $command): bool
    {
        if (array_key_exists($command, $this->installedBinaries)) {
            return $this->installedBinaries[$command];
        }
        $osCommand = OsInfo::isWindows() ? ['where', $command] : [
            'which',
            $command,
        ];
        $exists = $this->execute($osCommand, null, null, false, null, null, false)
            ->isSuccessful();
        $this->installedBinaries[$command] = $exists;
        return $exists;
    }

    public function checkRequiredBinariesExist(array $binaries = []): void
    {
        foreach ($binaries as $binary) {
            if (!$this->commandExists($binary)) {
                throw new AcquiaCliException("The required binary `$binary` does not exist. Install it and ensure it exists in a location listed in your system \$PATH");
            }
        }
    }

    /**
     * Executes a buffered command.
     */
    public function execute(array $cmd, ?callable $callback = null, ?string $cwd = null, ?bool $printOutput = true, ?float $timeout = null, ?array $env = null, bool $stdin = true): Process
    {
        $process = new Process($cmd);
        $process = $this->configureProcess($process, $cwd, $printOutput, $timeout, $env, $stdin);
        return $this->executeProcess($process, $callback, $printOutput);
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
     * @param array|null $env
     */
    public function executeFromCmd(string $cmd, ?callable $callback = null, ?string $cwd = null, ?bool $printOutput = true, ?int $timeout = null, ?array $env = null): Process
    {
        $process = Process::fromShellCommandline($cmd);
        $process = $this->configureProcess($process, $cwd, $printOutput, $timeout, $env);

        return $this->executeProcess($process, $callback, $printOutput);
    }

    /**
     * @param array|null $env
     */
    private function configureProcess(Process $process, ?string $cwd = null, ?bool $printOutput = true, ?float $timeout = null, ?array $env = null, bool $stdin = true): Process
    {
        if (function_exists('posix_isatty') && $stdin && !@posix_isatty(STDIN)) {
            $process->setInput(STDIN);
        }
        if ($cwd) {
            $process->setWorkingDirectory($cwd);
        }
        if ($printOutput) {
            $process->setTty($this->useTty());
        }
        if ($env) {
            $process->setEnv($env);
        }
        $process->setTimeout($timeout);

        return $process;
    }

    private function executeProcess(Process $process, ?callable $callback = null, ?bool $printOutput = true): Process
    {
        if ($callback === null && $printOutput !== false) {
            $callback = function (mixed $type, mixed $buffer): void {
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
    public function getFilesystem(): Filesystem
    {
        return new Filesystem();
    }

    /**
     * Returns a finder object.
     */
    public function getFinder(): Finder
    {
        return new Finder();
    }

    /**
     * Reads to a file from the local system.
     */
    public function readFile(string $filename): string
    {
        // @todo remove this blasphemy once upstream issue is fixed
        // @ see https://github.com/thecodingmachine/safe/issues/120
        return @file_get_contents($this->getLocalFilepath($filename));
    }

    public function getLocalFilepath(string $filepath): string
    {
        return $this->fixFilename($filepath);
    }

    /**
     * Determine whether the use of a tty is appropriate.
     */
    public function useTty(): bool
    {
        if (isset($this->isTty)) {
            return $this->isTty;
        }

        // If we are not in interactive mode, then never use a tty.
        if (!$this->input->isInteractive()) {
            return false;
        }

        // If we are in interactive mode (or at least the user did not
        // specify -n / --no-interaction), then also prevent the use
        // of a tty if stdout is redirected.
        // Otherwise, let the local machine helper decide whether to use a tty.
        if (function_exists('posix_isatty')) {
            return (posix_isatty(STDOUT) && @posix_isatty(STDIN));
        }

        return false;
    }

    public function setIsTty(?bool $isTty): void
    {
        $this->isTty = $isTty;
    }

    /**
     * Writes to a file on the local system.
     */
    public function writeFile(string $filename, string|StreamInterface $content): void
    {
        $this->getFilesystem()
            ->dumpFile($this->getLocalFilepath($filename), $content);
    }

    /**
     * Accepts a filename/full path and localizes it to the user's system.
     */
    private function fixFilename(string $filename): string
    {
        // '~' is only an alias for $HOME if it's at the start of the path.
        // On Windows, '~' (not as an alias) can appear other places in the path.
        return preg_replace('/^~/', self::getHomeDir(), $filename);
    }

    /**
     * Returns the appropriate home directory.
     *
     * @see https://github.com/pantheon-systems/terminus/blob/1d89e20dd388dc08979a1bc52dfd142b26c03dcf/src/Config/DefaultsConfig.php#L99
     */
    public static function getHomeDir(): string
    {
        $home = getenv('HOME');
        if (!$home) {
            $system = '';
            if (getenv('MSYSTEM')) {
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

    public static function getConfigDir(): string
    {
        $home = self::getHomeDir();
        $legacyDir = Path::join($home, '.acquia');
        if (file_exists($legacyDir)) {
            return $legacyDir;
        }
        if ($xdgHome = getenv('XDG_CONFIG_HOME')) {
            return Path::join($xdgHome, 'acquia');
        }
        return Path::join($home, '.config', 'acquia');
    }

    /**
     * Get the project root directory for the working directory.
     *
     * This method assumes you are running `acli` in a directory containing a
     * Drupal docroot either as a sibling or parent(N) of the working
     * directory.
     *
     * Typically, the root directory would also be a Git repository root,
     * though it doesn't have to be (such as for brand-new projects that
     * haven't initialized Git yet).
     */
    public static function getProjectDir(): ?string
    {
        $possibleProjectRoots = [
            getcwd(),
        ];
        // Check for PWD - some local environments will not have this key.
        if (getenv('PWD') && !in_array(getenv('PWD'), $possibleProjectRoots, true)) {
            array_unshift($possibleProjectRoots, getenv('PWD'));
        }
        foreach ($possibleProjectRoots as $possibleProjectRoot) {
            if ($projectRoot = self::findDirectoryContainingFiles($possibleProjectRoot, ['docroot'])) {
                return realpath($projectRoot);
            }
        }

        return null;
    }

    /**
     * Traverses file system upwards in search of a given file.
     *
     * Begins searching for $file in $workingDirectory and climbs up
     * directories
     * $maxHeight times, repeating search.
     *
     * @return bool|string FALSE if file was not found. Otherwise, the
     *     directory path containing the file.
     */
    private static function findDirectoryContainingFiles(string $workingDirectory, array $files, int $maxHeight = 10): bool|string
    {
        $filePath = $workingDirectory;
        for ($i = 0; $i <= $maxHeight; $i++) {
            if (self::filesExist($filePath, $files)) {
                return $filePath;
            }

            $filePath = dirname($filePath);
        }

        return false;
    }

    /**
     * Determines if an array of files exists in a particular directory.
     */
    private static function filesExist(string $dir, array $files): bool
    {
        foreach ($files as $file) {
            if (file_exists(Path::join($dir, $file))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if a browser is available on the local machine.
     */
    public function isBrowserAvailable(): bool
    {
        if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
            return false;
        }
        if (getenv('DISPLAY')) {
            return true;
        }
        if (OsInfo::isWindows() || OsInfo::isApple()) {
            return true;
        }

        return false;
    }

    /**
     * Starts a background browser/tab for the current site or a specified URL.
     *
     * Exclude from mutation testing as we don't want real browser windows
     * opened.
     *
     * @param string|null $uri Optional URI or site path to open in browser. If
     *     omitted, or if a site path is specified, the current site home page
     *     uri will be prepended if the site's hostname resolves.
     * @param string|null $browser The command to run to launch a browser.
     * @return bool TRUE if browser was opened. FALSE if browser was disabled
     *     by the user or a default browser could not be found.
     * @infection-ignore-all
     */
    public function startBrowser(?string $uri = null, ?string $browser = null): bool
    {
        // We can only open a browser if we have a DISPLAY environment variable on
        // POSIX or are running Windows or OS X.
        if (!$this->isBrowserAvailable()) {
            $this->logger->info('No graphical display appears to be available, not starting browser.');
            return false;
        }
        $host = parse_url($uri, PHP_URL_HOST);

        // Validate that the host part of the URL resolves, so we don't attempt to
        // open the browser for http://default or similar invalid hosts.
        $hostError = (gethostbynamel($host) === false);
        $ipError = (ip2long($host) && gethostbyaddr($host) === $host);
        if ($hostError || $ipError) {
            $this->logger->warning(
                '!host does not appear to be a resolvable hostname or IP, not starting browser.',
                ['!host' => $host]
            );

            return false;
        }
        if ($browser === null) {
            // See if we can find an OS helper to open URLs in default browser.
            if ($this->commandExists('xdg-open')) {
                // Linux.
                $browser = 'xdg-open';
            } elseif ($this->commandExists('open')) {
                // Darwin.
                $browser = 'open';
            } elseif (OsInfo::isWindows()) {
                $browser = 'start';
            } else {
                $this->logger->warning('Could not find a browser on your local machine. Check that one of <options=bold>xdg-open</>, <options=bold>open</>, or <options=bold>start</> are installed.');
                return false;
            }
        }
        if ($browser) {
            $this->io->info("Opening $uri");
            $this->executeFromCmd("$browser $uri");

            return true;
        }
        return false;
    }
}
