<?php

declare(strict_types=1);

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SshHelper implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * SshHelper constructor.
     */
    public function __construct(
        private readonly OutputInterface $output,
        private readonly LocalMachineHelper $localMachineHelper,
        LoggerInterface $logger
    ) {
        $this->setLogger($logger);
    }

    /**
     * Execute the command in a remote environment.
     */
    public function executeCommand(string $sshUrl, array $commandArgs, bool $printOutput = true, ?int $timeout = null, ?array $env = null): Process
    {
        $commandSummary = $this->getCommandSummary($commandArgs);

        // Remove site_env arg.
        unset($commandArgs['alias']);
        $process = $this->sendCommand($sshUrl, $commandArgs, $printOutput, $timeout, $env);

        $this->logger->debug('Command: {command} [Exit: {exit}]', [
            'command' => $commandSummary,
            'env' => $sshUrl,
            'exit' => $process->getExitCode(),
        ]);

        if (!$process->isSuccessful() && $process->getExitCode() === 255) {
            throw new AcquiaCliException($process->getOutput() . $process->getErrorOutput());
        }

        return $process;
    }

    private function sendCommand(string $url, array $command, bool $printOutput, ?int $timeout = null, ?array $env = null): Process
    {
        $command = array_values($this->getSshCommand($url, $command));
        $this->localMachineHelper->checkRequiredBinariesExist(['ssh']);

        return $this->localMachineHelper->execute($command, $this->getOutputCallback(), null, $printOutput, $timeout, $env);
    }

    /**
     * Return the first item of the $commandArgs that is not an option.
     */
    private function firstArguments(array $commandArgs): string
    {
        $result = '';
        while (!empty($commandArgs)) {
            $first = array_shift($commandArgs);
            if ($first !== '' && $first[0] === '-') {
                return $result;
            }
            $result .= " $first";
        }

        return $result;
    }

    private function getOutputCallback(): callable
    {
        if ($this->localMachineHelper->useTty() === false) {
            $output = $this->output;

            return static function (mixed $type, mixed $buffer) use ($output): void {
                $output->write($buffer);
            };
        }

        return static function (mixed $type, mixed $buffer): void {
        };
    }

    /**
     * Return a summary of the command that does not include the
     * arguments. This avoids potential information disclosure in
     * CI scripts.
     */
    private function getCommandSummary(array $commandArgs): string
    {
        return $this->firstArguments($commandArgs);
    }

    /**
     * @return array<mixed>
     */
    private function getConnectionArgs(string $url): array
    {
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
     * @return array<mixed>
     */
    private function getSshCommand(string $url, array $command): array
    {
        return array_merge($this->getConnectionArgs($url), $command);
    }
}
