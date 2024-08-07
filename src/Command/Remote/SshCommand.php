<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Remote;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command to proxy Drush commands on an environment using SSH.
 */
#[RequireAuth]
#[AsCommand(name: 'remote:ssh', description: 'Use SSH to open a shell or run a command in a Cloud Platform environment', aliases: ['ssh'])]
final class SshCommand extends SshBaseCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('alias', InputArgument::REQUIRED, 'Alias for application & environment in the format `app-name.env`')
            ->addArgument('ssh_command', InputArgument::IS_ARRAY, 'Command to run via SSH (if not provided, opens a shell in the site directory)')
            ->addUsage("myapp.dev # open a shell in the myapp.dev environment")
            ->addUsage("myapp.dev -- ls -al # list files in the myapp.dev environment and return");
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $alias = $input->getArgument('alias');
        $alias = $this->normalizeAlias($alias);
        $alias = self::validateEnvironmentAlias($alias);
        $environment = $this->getEnvironmentFromAliasArg($alias);
        if (!isset($environment->sshUrl)) {
            throw new AcquiaCliException('Cannot determine environment SSH URL. Check that you have SSH permissions on this environment.');
        }
        $sshCommand = [
            'cd /var/www/html/' . $alias,
        ];
        $arguments = $input->getArguments();
        if (empty($arguments['ssh_command'])) {
            $sshCommand[] = 'exec $SHELL -l';
        } else {
            $sshCommand[] = implode(' ', $arguments['ssh_command']);
        }
        $sshCommand = (array) implode('; ', $sshCommand);
        return $this->sshHelper->executeCommand($environment->sshUrl, $sshCommand)
            ->getExitCode();
    }
}
