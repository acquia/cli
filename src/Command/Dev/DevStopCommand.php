<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Dev;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'dev:stop', description: 'Stop the local development environment')]
final class DevStopCommand extends PullCommandBase
{
    use DevStackTrait;

    /** @infection-ignore-all */
    protected function configure(): void
    {
        $this
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'The project directory (defaults to the current directory)')
            ->setHelp('Stops the ddev-based local environment created by <info>acli dev:init</info>. Your code and database are kept; <info>acli dev:start</info> brings the site back.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dir = $this->resolveProjectDir($input);
        $this->localMachineHelper->checkRequiredBinariesExist(['ddev']);
        $this->checklist->addItem('Stopping the local environment');
        $process = $this->localMachineHelper->execute(['ddev', 'stop'], $this->getOutputCallback($output, $this->checklist), $this->dir, false, null);
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException('Unable to stop ddev. {message}', ['message' => $process->getErrorOutput()]);
        }
        $this->checklist->completePreviousItem();
        $this->io->success('Local environment stopped. Run `acli dev:start` to bring it back.');

        return Command::SUCCESS;
    }
}
