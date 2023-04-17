<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SshHelper implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * SshHelper constructor.
   */
  public function __construct(
      private OutputInterface $output,
      private LocalMachineHelper $localMachineHelper,
      LoggerInterface $logger
  ) {
    $this->setLogger($logger);
  }

  /**
   * Execute the command in a remote environment.
   *
   * @param array $command_args
   * @param int|null $timeout
   */
  public function executeCommand(EnvironmentResponse|string $target, array $command_args, bool $print_output = TRUE, int $timeout = NULL): Process {
    $command_summary = $this->getCommandSummary($command_args);

    if (is_a($target, EnvironmentResponse::class)) {
      $target = $target->sshUrl;
    }

    // Remove site_env arg.
    unset($command_args['alias']);
    $process = $this->sendCommand($target, $command_args, $print_output, $timeout);

    $this->logger->debug('Command: {command} [Exit: {exit}]', [
      'env' => $target,
      'command' => $command_summary,
      'exit' => $process->getExitCode(),
    ]);

    if (!$process->isSuccessful() && $process->getExitCode() === 255) {
      throw new AcquiaCliException($process->getOutput() . $process->getErrorOutput());
    }

    return $process;
  }

  private function sendCommand($url, $command, $print_output, $timeout = NULL): Process {
    $command = array_values($this->getSshCommand($url, $command));
    $this->localMachineHelper->checkRequiredBinariesExist(['ssh']);

    return $this->localMachineHelper->execute($command, $this->getOutputCallback(), NULL, $print_output, $timeout);
  }

  /**
   * Return the first item of the $command_args that is not an option.
   *
   * @param array $command_args
   */
  private function firstArguments(array $command_args): string {
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
   */
  private function getOutputCallback(): callable {
    if ($this->localMachineHelper->useTty() === FALSE) {
      $output = $this->output;

      return static function ($type, $buffer) use ($output): void {
        $output->write($buffer);
      };
    }

    return static function ($type, $buffer): void {};
  }

  /**
   * Return a summary of the command that does not include the
   * arguments. This avoids potential information disclosure in
   * CI scripts.
   *
   * @param array $command_args
   */
  private function getCommandSummary(array $command_args): string {
    return $this->firstArguments($command_args);
  }

  /**
   * @param $url
   * @return array SSH connection string
   */
  private function getConnectionArgs($url): array {
    return [
      'ssh',
      $url,
      '-t',
      '-o StrictHostKeyChecking=no',
      '-o AddressFamily inet',
      '-o LogLevel=ERROR',
    ];
  }

  /**
   * @param $command
   * @return array
   */
  private function getSshCommand(string $url, $command): array {
    return array_merge($this->getConnectionArgs($url), $command);
  }

}
