<?php

namespace Acquia\Ads\Helpers;

use Acquia\Ads\Exception\AdsException;
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

    public function commandExists($command): bool
    {
        return $this->exec(['type', $command])->isSuccessful();
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

    public function executeFromCmd($cmd, $callback = null, $cwd = null, $print_output = true): Process
    {
        $process = Process::fromShellCommandline($cmd);
        return $this->executeProcess($process, $callback, $cwd, $print_output);
    }

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

    protected function isInteractive()
    {
        if (function_exists('posix_isatty')) {
            $useTty = $this->useTty();
            if (!$useTty) {
                $useTty = (posix_isatty(STDOUT) && posix_isatty(STDIN));
            }
            if (!posix_isatty(STDIN)) {
                return false;
            }
        }
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
        return file_get_contents($this->fixFilename($filename));
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
        $this->getFilesystem()->dumpFile($this->fixFilename($filename), $content);
    }

    /**
     * Accepts a filename/full path and localizes it to the user's system.
     *
     * @param string $filename
     *
     * @return string
     */
    protected function fixFilename($filename): string
    {
        return str_replace('~', $this->getHomeDir(), $filename);
    }

    /**
     * Returns a set-up process object.
     *
     * @param array $cmd The command to execute
     *
     * @return Process
     */
    protected function getProcess($cmd): Process
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
    public function getHomeDir(): string
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
}
