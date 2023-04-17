<?php

namespace Acquia\Cli\Command\Remote;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DrushCommand
 * A command to proxy Drush commands on an environment using SSH.
 *
 * @package Acquia\Cli\Commands\Remote
 */
class DrushCommand extends SshBaseCommand {

  protected static $defaultName = 'remote:drush';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setAliases(['drush', 'dr'])
      ->setDescription('Run a Drush command remotely on a application\'s environment')
      ->setHelp('<fg=black;bg=cyan>Pay close attention to the argument syntax! Note the usage of <options=bold;bg=cyan>--</> to separate the drush command arguments and options.</>')
      ->addArgument('alias', InputArgument::REQUIRED, 'Alias for application & environment in the format `app-name.env`')
      ->addArgument('drush_command', InputArgument::IS_ARRAY, 'Drush command')
      ->addUsage('<app>.<env> -- <command>')
      ->addUsage('myapp.dev -- uli 1')
      ->addUsage('myapp.dev -- status --fields=db-status');
  }

  protected function execute(InputInterface $input, OutputInterface $output): ?int {
    $alias = $input->getArgument('alias');
    $alias = $this->normalizeAlias($alias);
    $alias = self::validateEnvironmentAlias($alias);
    $environment = $this->getEnvironmentFromAliasArg($alias);

    $acli_arguments = $input->getArguments();
    $drush_command_arguments = [
      "cd /var/www/html/{$alias}/docroot; ",
      'drush',
      implode(' ', (array) $acli_arguments['drush_command']),
    ];

    return $this->sshHelper->executeCommand($environment, $drush_command_arguments)->getExitCode();
  }

}
