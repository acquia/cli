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
        return $this->doExecuteCommand($sshUrl, $commandArgs, $printOutput, $timeout, $env, true);
    }

    /**
     * Execute the command in a remote environment without allocating a pty.
     *
     * Required whenever input is streamed: TTY mode discards piped input.
     */
    public function executeCommandWithoutTty(string $sshUrl, array $commandArgs, bool $printOutput = true, ?int $timeout = null, ?array $env = null, mixed $input = null): Process
    {
        return $this->doExecuteCommand($sshUrl, $commandArgs, $printOutput, $timeout, $env, false, $input);
    }

    private function doExecuteCommand(string $sshUrl, array $commandArgs, bool $printOutput, ?int $timeout, ?array $env, bool $tty, mixed $input = null): Process
    {
        $commandSummary = $this->getCommandSummary($commandArgs);

        // Remove site_env arg.
        unset($commandArgs['alias']);
        $process = $this->sendCommand($sshUrl, $commandArgs, $printOutput, $timeout, $env, $tty, $input);

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

    private function sendCommand(string $url, array $command, bool $printOutput, ?int $timeout, ?array $env, bool $tty, mixed $input): Process
    {
        // @infection-ignore-all — array_values() normalises string keys that
        // array_merge would otherwise preserve.
        $command = array_values($this->getSshCommand($url, $command, $tty));
        $this->localMachineHelper->checkRequiredBinariesExist(['ssh']);

        // Dropping -t is not enough: configureProcess() would set TTY mode or
        // wire acli's STDIN, either of which replaces a caller's input.
        if ($tty) {
            // Unchanged, argument count included, so existing tests still guard it.
            return $this->localMachineHelper->execute($command, $this->getOutputCallback(), null, $printOutput, $timeout, $env);
        }

        return $this->localMachineHelper->execute($command, $this->getOutputCallback(false, $printOutput), null, false, $timeout, $env, false, $input);
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

    /**
     * Choose how a remote command's output is surfaced: without a pty it
     * arrives through pipes and must be written explicitly.
     */
    private function getOutputCallback(bool $tty = true, bool $printOutput = true): callable
    {
        $silent = static function (mixed $type, mixed $buffer): void {
        };

        if (!$printOutput) {
            return $silent;
        }

        // The pty writes to the terminal itself; writing again would duplicate.
        if ($tty && $this->localMachineHelper->useTty() !== false) {
            return $silent;
        }

        $output = $this->output;

        return static function (mixed $type, mixed $buffer) use ($output): void {
            $output->write($buffer);
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
    private function getConnectionArgs(string $url, bool $tty): array
    {
        $args = ['ssh', $url];
        if ($tty) {
            $args[] = '-t';
        }

        // The non-TTY list must differ by the pty flag alone.
        return array_merge($args, [
            '-o StrictHostKeyChecking=accept-new',
            '-o AddressFamily inet',
            '-o LogLevel=ERROR',
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function getSshCommand(string $url, array $command, bool $tty): array
    {
        return array_merge($this->getConnectionArgs($url, $tty), $command);
    }
}
