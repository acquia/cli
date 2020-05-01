<?php

namespace Acquia\Ads\Helpers;

use drupol\phposinfo\OsInfo;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Class ShellExecHelper
 *
 * A helper for executing commands on the local client. A wrapper for 'exec' and 'passthru'.
 *
 * @package Acquia\Ads\Helpers
 */
class LocalMachineHelper
{
    use LoggerAwareTrait;

    private $output;
    private $input;

    public function __construct(InputInterface $input, OutputInterface $output, $logger)
    {
        $this->input = $input;
        $this->output = $output;
        $this->setLogger($logger);
    }

    /**
     * Executes the given command on the local machine and return the exit code and output.
     *
     * @param array $cmd The command to execute
     * @param null $callback
     *
     * @return Process
     */
    public function exec($cmd, $callback = null): Process
    {
        $process = $this->getProcess($cmd);
        $process->run($callback);

        return $process;
    }

    /**
     * @param $command
     *
     * @return bool
     */
    public function commandExists($command): bool
    {
        $os_command = OsInfo::isWindows() ? ['where', $command] : ['command', '-v', $command];
        return $this->exec($os_command)->isSuccessful();
    }

    /**
     * Executes a buffered command.
     *
     * @param array $cmd The command to execute
     * @param callable $callback A function to run while waiting for the process to complete
     * @param null $cwd
     *
     * @return Process
     */
    public function execute($cmd, $callback = null, $cwd = null, $print_output = true): Process
    {
        $process = $this->getProcess($cmd);
        return $this->executeProcess($process, $callback, $cwd, $print_output);
    }

    /**
     * @param $cmd
     * @param null $callback
     * @param null $cwd
     * @param bool $print_output
     *
     * @return \Symfony\Component\Process\Process
     */
    public function executeFromCmd($cmd, $callback = null, $cwd = null, $print_output = true): Process
    {
        $process = Process::fromShellCommandline($cmd);
        return $this->executeProcess($process, $callback, $cwd, $print_output);
    }

    /**
     * @param \Symfony\Component\Process\Process $process
     * @param null $callback
     * @param null $cwd
     * @param bool $print_output
     *
     * @return \Symfony\Component\Process\Process
     */
    protected function executeProcess(Process $process, $callback = null, $cwd = null, $print_output = true): Process
    {
        if (function_exists('posix_isatty') && !posix_isatty(STDIN)) {
            $process->setInput(STDIN);
        }
        if ($cwd) {
            $process->setWorkingDirectory($cwd);
        }
        if ($print_output) {
            $process->setTty($this->useTty());
        }
        $process->start(null);
        $process->wait($callback);

        $this->logger->notice('Command: {command} [Exit: {exit}]', [
          'command' => $process->getCommandLine(),
          'exit' => $process->getExitCode(),
        ]);

        return $process;
    }

    /**
     * Returns a set-up filesystem object.
     *
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        return new Filesystem();
    }

    /**
     * Returns a finder object
     *
     * @return Finder
     */
    public function getFinder(): Finder
    {
        return new Finder();
    }

    /**
     * Reads to a file from the local system.
     *
     * @param string $filename Name of the file to read
     *
     * @return string Content read from that file
     */
    public function readFile($filename): string
    {
        return file_get_contents($this->getLocalFilepath($filename));
    }

    /**
     * @param $filepath
     *
     * @return string
     */
    public function getLocalFilepath($filepath): string
    {
        return $this->fixFilename($filepath);
    }

    /**
     * Determine whether the use of a tty is appropriate.
     *
     * @return bool
     */
    public function useTty(): bool
    {
        // If we are not in interactive mode, then never use a tty.
        if (!$this->input->isInteractive()) {
            return false;
        }

        // If we are in interactive mode (or at least the user did not
        // specify -n / --no-interaction), then also prevent the use
        // of a tty if stdout is redirected.
        // Otherwise, let the local machine helper decide whether to use a tty.
        if (function_exists('posix_isatty')) {
            return (posix_isatty(STDOUT) && posix_isatty(STDIN));
        }

        return false;
    }

    /**
     * Writes to a file on the local system.
     *
     * @param string $filename Name of the file to write to
     * @param string $content Content to write to the file
     */
    public function writeFile($filename, $content): void
    {
        $this->getFilesystem()->dumpFile($this->getLocalFilepath($filename), $content);
    }

    /**
     * Accepts a filename/full path and localizes it to the user's system.
     *
     * @param string $filename
     *
     * @return string
     */
    private function fixFilename($filename): string
    {
        return str_replace('~', self::getHomeDir(), $filename);
    }

    /**
     * Returns a set-up process object.
     *
     * @param array $cmd The command to execute
     *
     * @return Process
     */
    private function getProcess($cmd): Process
    {
        $process = new Process($cmd);
        $process->setTimeout(600);

        return $process;
    }

    /**
     * Returns the appropriate home directory.
     *
     * Adapted from Ads Package Manager by Ed Reel
     * @return string
     * @author Ed Reel <@uberhacker>
     * @url    https://github.com/uberhacker/tpm
     *
     */
    public static function getHomeDir(): string
    {
        $home = getenv('HOME');
        if (!$home) {
            $system = '';
            if (getenv('MSYSTEM') !== null) {
                $system = strtoupper(substr(getenv('MSYSTEM'), 0, 4));
            }
            if ($system != 'MING') {
                $home = getenv('HOMEPATH');
            }
        }

        return $home;
    }

    /**
     * Starts a background browser/tab for the current site or a specified URL.
     *
     * Uses a non-blocking Process call, so Drush execution will continue.
     *
     * @param $uri
     *   Optional URI or site path to open in browser. If omitted, or if a site path
     *   is specified, the current site home page uri will be prepended if the site's
     *   hostname resolves.
     * @param int $sleep
     * @param bool $port
     * @param bool $browser
     *
     * @return bool
     *   TRUE if browser was opened. FALSE if browser was disabled by the user or a
     *   default browser could not be found.
     */
    public function startBrowser($uri = null, $sleep = 0, $port = false, $browser = true): bool
    {
        if ($browser) {
            // We can only open a browser if we have a DISPLAY environment variable on
            // POSIX or are running Windows or OS X.
            if (!getenv('DISPLAY') && !OsInfo::isWindows() && !OsInfo::isApple()) {
                $this->logger->info('No graphical display appears to be available, not starting browser.');

                return false;
            }
            $host = parse_url($uri, PHP_URL_HOST);
            if (!$host) {
                // Build a URI for the current site, if we were passed a path.
                $site = $this->uri;
                $host = parse_url($site, PHP_URL_HOST);
                $uri = $site . '/' . ltrim($uri, '/');
            }
            // Validate that the host part of the URL resolves, so we don't attempt to
            // open the browser for http://default or similar invalid hosts.
            $hosterror = (gethostbynamel($host) === false);
            $iperror = (ip2long($host) && gethostbyaddr($host) == $host);
            if ($hosterror || $iperror) {
                $this->logger->warning(
                    '!host does not appear to be a resolvable hostname or IP, not starting browser.',
                    ['!host' => $host]
                );

                return false;
            }
            if ($port) {
                $uri = str_replace($host, "localhost:$port", $uri);
            }
            if ($browser === true) {
                // See if we can find an OS helper to open URLs in default browser.
                if ($this->commandExists('xdg-open')) {
                    $browser = 'xdg-open';
                } else {
                    if ($this->commandExists('open')) {
                        $browser = 'open';
                    } else {
                        if ($this->commandExists('start')) {
                            $browser = 'start';
                        } else {
                            $this->logger->warning('Could not find a browser on your local machine.');
                            return false;
                        }
                    }
                }
            }

            if ($browser) {
                $this->logger->info('Opening browser !browser at !uri', ['!browser' => $browser, '!uri' => $uri]);
                $args = [];

                if ($sleep) {
                    $args = ['sleep', $sleep, '&&'];
                }
                // @todo We implode because quoting is messing up the sleep.
                $process = new Process(array_merge($args, [$browser, $uri]));
                $process->run();

                return true;
            }
        }

        return false;
    }
}
