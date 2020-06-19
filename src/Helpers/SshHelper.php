<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SshHelper {

  /** @var \Symfony\Component\Console\Output\OutputInterface */
  private $output;

  /**
   * @var \Acquia\Cli\Helpers\LocalMachineHelper
   */
  private $localMachineHelper;

  /**
   * SshHelper constructor.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function __construct(OutputInterface $output, LocalMachineHelper $localMachineHelper) {
    $this->output = $output;
    $this->localMachineHelper = $localMachineHelper;
  }

  /**
   * Execute the command remotely.
   *
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   * @param array $command_args
   *
   * @param bool $print_output
   *
   * @return \Symfony\Component\Process\Process
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function executeCommand($environment, array $command_args, $print_output = TRUE): Process {
    $command_summary = $this->getCommandSummary($command_args);

    // Remove site_env arg.
    unset($command_args['alias']);
    $process = $this->sendCommandViaSsh($environment, $command_args, $print_output);

    /** @var \Acquia\Cli\AcquiaCliApplication $application */
    $logger = new ConsoleLogger($this->output);
    $logger->notice('Command: {command} [Exit: {exit}]', [
      'env' => $environment->name,
      'command' => $command_summary,
      'exit' => $process->getExitCode(),
    ]);

    if (!$process->isSuccessful() && $process->getExitCode() === 255) {
      throw new AcquiaCliException($process->getOutput() . $process->getErrorOutput());
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
   * @param $print_output
   *
   * @return \Symfony\Component\Process\Process
   * @throws \Exception
   */
  protected function sendCommandViaSsh($environment, $command, $print_output): Process {
    $this->localMachineHelper->setIsTty(TRUE);
    $command = array_values($this->getSshCommand($environment, $command));

    return $this->localMachineHelper->execute($command, $this->getOutputCallback(), NULL, $print_output);
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
      if ($first != '' && $first[0] == '-') {
        return $result;
      }
      $result .= " $first";
    }

    return $result;
  }

  /**
   * @return \Closure
   * @throws \Exception
   */
  private function getOutputCallback(): callable {
    if ($this->localMachineHelper->useTty() === FALSE) {
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
