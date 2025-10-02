<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'app:new:local', description: 'Create a new Drupal or Next.js project', aliases: ['new'])]
final class NewCommand extends CommandBase
{
    /**
     * @var string[]
     */
    private static array $distros = [
        'acquia_drupal_cms' => 'acquia/drupal-cms-project',
        'acquia_drupal_recommended' => 'acquia/drupal-recommended-project',
    ];
    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::OPTIONAL, 'The destination directory')
            ->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'The project template', null, array_keys(self::$distros))
            ->addUsage('-t acquia_drupal_recommended');
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output->writeln('Acquia recommends most customers use <options=bold>acquia/drupal-recommended-project</> to setup a Drupal project, which includes useful utilities such as Acquia Connector.');
        $this->output->writeln('<options=bold>acquia/drupal-cms-project</> is Drupal CMS scaffolded to work with Acquia hosting.');

        if ($input->hasOption('template') && $input->getOption('template')) {
            $project = $input->getOption('template');
        } else {
            $project = $this->io->choice('Choose a starting project', array_values(self::$distros), self::$distros['acquia_drupal_recommended']);
            $project = array_search($project, self::$distros, true);
        }

        if ($input->hasArgument('directory') && $input->getArgument('directory')) {
            $dir = Path::canonicalize($input->getArgument('directory'));
            $dir = Path::makeAbsolute($dir, getcwd());
        } elseif (AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
            $dir = '/home/ide/project';
        } else {
            $dir = Path::makeAbsolute($project, getcwd());
        }

        $output->writeln('<info>Creating project. This may take a few minutes.</info>');

        $successMessage = "<info>New 💧 Drupal project created in $dir. 🎉</info>";
        $this->localMachineHelper->checkRequiredBinariesExist(['composer']);
        $this->createDrupalProject(self::$distros[$project], $dir);

        $this->initializeGitRepository($dir);

        $output->writeln('');
        $output->writeln($successMessage);

        return Command::SUCCESS;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function createDrupalProject(string $project, string $dir): void
    {
        $process = $this->localMachineHelper->execute([
            'composer',
            'create-project',
            $project,
            $dir,
            '--no-interaction',
        ]);
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException("Unable to create new project.");
        }
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function initializeGitRepository(string $dir): void
    {
        if (
            $this->localMachineHelper->getFilesystem()
            ->exists(Path::join($dir, '.git'))
        ) {
            $this->logger->debug('.git directory detected, skipping Git repo initialization');
            return;
        }
        $this->localMachineHelper->checkRequiredBinariesExist(['git']);
        $this->localMachineHelper->execute([
            'git',
            'init',
            '--initial-branch=main',
        ], null, $dir);

        $this->localMachineHelper->execute([
            'git',
            'add',
            '-A',
        ], null, $dir);

        $this->localMachineHelper->execute([
            'git',
            'commit',
            '--message',
            'Initial commit.',
            '--quiet',
        ], null, $dir);
        // @todo Check that this was successful!
    }
}
