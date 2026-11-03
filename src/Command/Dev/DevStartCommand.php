<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Dev;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'dev:start', description: 'Start the local development environment')]
final class DevStartCommand extends PullCommandBase
{
    use DevStackTrait;

    /** @infection-ignore-all */
    protected function configure(): void
    {
        $this
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'The project directory (defaults to the current directory)')
            ->setHelp('Starts the ddev-based local environment created by <info>acli dev:init</info> and prints the site URL. For everything else — drush, logs, ssh — use ddev directly.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dir = $this->resolveProjectDir($input);
        $this->localMachineHelper->checkRequiredBinariesExist(['ddev']);
        $this->startLocalEnvironment($output);
        $url = $this->getLocalSiteUrl();
        $this->checkSiteResponds($url);
        $this->io->success("Your local site is running: $url");
        $this->io->writeln([
            '  ddev drush uli   Get a one-time login link for your site',
            '  acli dev:stop    Stop the local environment',
        ]);

        return Command::SUCCESS;
    }
}
