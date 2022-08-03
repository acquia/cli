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

  /** @var OutputInterface */
  private $output;

  /**
   * @var LocalMachineHelper
   */
  private $localMachineHelper;

  /**
   * SshHelper constructor.
   *
   * @param OutputInterface $output
   * @param LocalMachineHelper $localMachineHelper
   * @param LoggerInterface $logger
   */
  public function __construct(
      OutputInterface $output,
      LocalMachineHelper $localMachineHelper,
      LoggerInterface $logger
  ) {
    $this->output = $output;
    $this->localMachineHelper = $localMachineHelper;
    $this->setLogger($logger);
  }

  /**
   * Execute the command in a remote environment.
   *
   * @param \AcquiaCloudApi\Response\EnvironmentResponse|string $target
   * @param array $command_args
   * @param bool $print_output
   * @param int|null $timeout
   *
   * @return \Symfony\Component\Process\Process
   * @throws \Acquia\Cli\Exception\AcquiaCliException
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

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  private function sendCommand($url, $command, $print_output, $timeout = NULL): Process {
    $command = array_values($this->getSshCommand($url, $command));
    $this->localMachineHelper->checkRequiredBinariesExist(['ssh']);

    return $this->localMachineHelper->execute($command, $this->getOutputCallback(), NULL, $print_output, $timeout);
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
      '-t',
      '-o StrictHostKeyChecking=no',
      '-o AddressFamily inet',
      '-o LogLevel=ERROR',
    ];
  }

  /**
   * @param string $url
   * @param $command
   *
   * @return array
   */
  private function getSshCommand(string $url, $command): array {
    return array_merge($this->getConnectionArgs($url), $command);
  }

}
