<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Output\Checklist;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pull:run-scripts', description: 'Execute post pull scripts (Added in 1.1.0).')]
final class PullScriptsCommand extends CommandBase
{
    protected Checklist $checklist;

    protected function configure(): void
    {
        $this
            ->acceptEnvironmentId()
            ->acceptSite()
            ->acceptSiteInstanceId()
            ->addOption('dir', null, InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->checklist = new Checklist($output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setDirAndRequireProjectCwd($input);
        $this->executeAllScripts($this->getOutputCallback($output, $this->checklist), $this->checklist);

        return Command::SUCCESS;
    }
}
