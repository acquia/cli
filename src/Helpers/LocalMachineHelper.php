<?php

namespace Acquia\Cli\Helpers;

use drupol\phposinfo\OsInfo;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Class LocalMachineHelper.
 *
 * A helper for executing commands on the local client. A wrapper for 'exec' and 'passthru'.
 *
 * @package Acquia\Cli\Helpers
 */
class LocalMachineHelper {
  use LoggerAwareTrait;

  private $output;
  private $input;
  private $isTty = NULL;

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $logger
   */
  public function __construct(InputInterface $input, OutputInterface $output, LoggerInterface $logger) {
    $this->input = $input;
    $this->output = $output;
    $this->setLogger($logger);
  }

  /**
   * @param $command
   *
   * @return bool
   */
  public function commandExists($command): bool {
    $os_command = OsInfo::isWindows() ? ['where', $command] : ['which', $command];
        // phpcs:ignore
        return $this->execute($os_command, NULL, NULL, FALSE)->isSuccessful();
  }

  /**
   * Executes a buffered command.
   *
   * @param array $cmd
   *   The command to execute.
   * @param callable $callback
   *   A function to run while waiting for the process to complete.
   * @param string $cwd
   * @param bool $print_output
   *
   * @return \Symfony\Component\Process\Process
   */
  public function execute($cmd, $callback = NULL, $cwd = NULL, $print_output = TRUE): Process {
    $process = $this->getProcess($cmd);
    return $this->executeProcess($process, $callback, $cwd, $print_output);
  }

  /**
   * @param \Symfony\Component\Process\Process $process
   * @param callable $callback
   * @param string $cwd
   * @param bool $print_output
   *
   * @return \Symfony\Component\Process\Process
   */
  protected function executeProcess(Process $process, $callback = NULL, $cwd = NULL, $print_output = TRUE): Process {
    if (function_exists('posix_isatty') && !posix_isatty(STDIN)) {
      $process->setInput(STDIN);
    }
    if ($cwd) {
      $process->setWorkingDirectory($cwd);
    }
    if ($print_output) {
      $process->setTty($this->useTty());
    }
    $process->start(NULL);
    $process->wait($callback);

    $this->logger->notice('Command: {command} [Exit: {exit}]', [
      'command' => $process->getCommandLine(),
      'exit' => $process->getExitCode(),
    ]);

    return $process;
  }

  /**
   * Determine whether the use of a tty is appropriate.
   *
   * @return bool
   */
  public function useTty(): bool {
    if (isset($this->isTty) && $this->isTty) {
      return TRUE;
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

  /**
   * Returns a set-up process object.
   *
   * @param array $cmd
   *   The command to execute.
   *
   * @return \Symfony\Component\Process\Process
   */
  private function getProcess($cmd): Process {
    $process = new Process($cmd);
    $process->setTimeout(600);

    return $process;
  }

  /**
   * Starts a background browser/tab for the current site or a specified URL.
   *
   * @param $uri
   *   Optional URI or site path to open in browser. If omitted, or if a site path
   *   is specified, the current site home page uri will be prepended if the site's
   *   hostname resolves.
   * @param string $browser The command to run to launch a browser.
   *
   * @return bool
   *   TRUE if browser was opened. FALSE if browser was disabled by the user or a
   *   default browser could not be found.
   */
  public function startBrowser($uri = NULL, $browser = NULL): bool {
    // We can only open a browser if we have a DISPLAY environment variable on
    // POSIX or are running Windows or OS X.
    if (!getenv('DISPLAY') && !OsInfo::isWindows() && !OsInfo::isApple()) {
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
        $browser = 'xdg-open';
      }
      else {
        if ($this->commandExists('open')) {
          $browser = 'open';
        }
        else {
          if ($this->commandExists('start')) {
            $browser = 'start';
          }
          else {
            $this->logger->warning('Could not find a browser on your local machine.');
            return FALSE;
          }
        }
      }
    }
    if ($browser) {
      $this->logger->info('Opening browser !browser at !uri', ['!browser' => $browser, '!uri' => $uri]);
      $process = new Process([$browser, $uri]);
      $process->run();

      return TRUE;
    }
    return FALSE;
  }

}
