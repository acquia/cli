<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pipelines;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pipelines:migrate:gitlab', description: 'Convert an acquia-pipelines.yml file to a generic .gitlab-ci.yml file', aliases: ['p:m:g'])]
final class PipelinesMigrateGitlabCommand extends CommandBase
{
    protected function configure(): void
    {
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to the directory containing the acquia-pipelines.yml file. Defaults to the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
