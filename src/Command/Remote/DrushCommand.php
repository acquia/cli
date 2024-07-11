<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Remote;

use Acquia\Cli\Attribute\RequireAuth;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command to proxy Drush commands on an environment using SSH.
 */
#[RequireAuth]
#[AsCommand(name: 'remote:drush', description: 'Run a Drush command remotely on a Cloud Platform environment', aliases: ['drush', 'dr'])]
final class DrushCommand extends SshBaseCommand
{
    protected function configure(): void
    {
        $this
        ->setHelp('<fg=black;bg=cyan>Pay close attention to the argument syntax! Note the usage of <options=bold;bg=cyan>--</> to separate the drush command arguments and options.</>')
        ->acceptEnvironmentId()
        ->addArgument('drush_command', InputArgument::IS_ARRAY, 'Drush command')
        ->addUsage('<app>.<env> -- <command>')
        ->addUsage('myapp.dev -- uli 1')
        ->addUsage('myapp.dev -- status --fields=db-status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $environment = $this->determineEnvironment($input, $output);
        $alias = self::getEnvironmentAlias($environment);
        $acliArguments = $input->getArguments();
        $drushArguments = (array) $acliArguments['drush_command'];
        // When available, provide the default domain to drush.
        if (!empty($environment->default_domain)) {
            // Insert at the beginning so a user-supplied --uri arg will override.
            array_unshift($drushArguments, "--uri=http://$environment->default_domain");
        }
        $drushCommandArguments = [
            "cd /var/www/html/$alias/docroot; ",
            'drush',
            implode(' ', $drushArguments),
        ];

        return $this->sshHelper->executeCommand($environment->sshUrl, $drushCommandArguments)->getExitCode();
    }
}
