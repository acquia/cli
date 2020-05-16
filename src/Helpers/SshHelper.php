<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\AcquiaCliApplication;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SshHelper {

  /** @var \Acquia\Cli\AcquiaCliApplication */
  protected $application;

  /** @var \Symfony\Component\Console\Output\OutputInterface */
  private $output;

  /**
   * SshHelper constructor.
   *
   * @param \Acquia\Cli\AcquiaCliApplication $application
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function __construct($application, OutputInterface $output) {
    $this->application = $application;
    $this->output = $output;
  }

  /**
   * @return \Acquia\Cli\AcquiaCliApplication
   */
  public function getApplication(): AcquiaCliApplication {
    return $this->application;
  }

  /**
   * Execute the command remotely.
   *
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   * @param array $command_args
   *
   * @return \Symfony\Component\Process\Process
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function executeCommand($environment, array $command_args): Process {
    $command_summary = $this->getCommandSummary($command_args);

    // Remove site_env arg.
    unset($command_args['alias']);
    $process = $this->sendCommandViaSsh($environment, $command_args);

    /** @var \Acquia\Cli\AcquiaCliApplication $application */
    $application = $this->getApplication();
    $application->getLogger()->notice('Command: {command} [Exit: {exit}]', [
      'env' => $environment->name,
      'command' => $command_summary,
      'exit' => $process->getExitCode(),
    ]);

    if (!$process->isSuccessful()) {
      throw new AcquiaCliException($process->getOutput());
    }

    return $process;
  }

  /**
   * Sends a command to an environment via SSH.
   *
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   * @param array $command
   *   The command to be run on the platform.
   *
   * @return \Symfony\Component\Process\Process
   */
  protected function sendCommandViaSsh($environment, $command): Process {
    $this->getApplication()->getLocalMachineHelper()->setIsTty(TRUE);
    $command = array_values($this->getSshCommand($environment, $command));

    return $this->getApplication()
      ->getLocalMachineHelper()
      ->execute($command, $this->getOutputCallback());
  }

  /**
   * Return the first item of the $command_args that is not an option.
   *
   * @param array $command_args
   *
   * @return string
   */
  private function firstArguments($command_args): string {
    $result = '';
    while (!empty($command_args)) {
      $first = array_shift($command_args);
      if (strlen($first) && $first[0] == '-') {
        return $result;
      }
      $result .= " $first";
    }

    return $result;
  }

  /**
   * @return \Closure
   */
  private function getOutputCallback(): callable {
    if ($this->getApplication()->getLocalMachineHelper()->useTty() === FALSE) {
      $output = $this->output;

      return static function ($type, $buffer) use ($output) {
        $output->write($buffer);
      };
    }

    return static function ($type, $buffer) {};
  }

  /**
   * Return a summary of the command that does not include the
   * arguments. This avoids potential information disclosure in
   * CI scripts.
   *
   * @param array $command_args
   *
   * @return string
   */
  private function getCommandSummary($command_args): string {
    return $this->firstArguments($command_args);
  }

  /**
   * @param $url
   *
   * @return array SSH connection string
   */
  private function getConnectionArgs($url): array {
    return [
      'ssh',
      $url,
      // Disable pseudo-terminal allocation.
      // '-T',
      '-o StrictHostKeyChecking=no',
      '-o AddressFamily inet',
      '-o LogLevel=ERROR',
    ];
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   * @param $command
   *
   * @return array
   */
  protected function getSshCommand($environment, $command): array {
    return array_merge($this->getConnectionArgs($environment->sshUrl), $command);
  }

}
