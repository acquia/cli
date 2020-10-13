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
class DrushCommand extends SSHBaseCommand {

  protected static $defaultName = 'remote:drush';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setAliases(['drush', 'dr'])
      ->setDescription('Run a Drush command remotely on a application\'s environment')
      ->setHelp('Pleases pay close attention to the argument syntax! Note the usage of <options=bold>--</> to separate the drush command arguments and options.')
      ->addArgument('alias', InputArgument::REQUIRED, 'Alias for application & environment in the format `app-name.env`')
      ->addArgument('drush_command', InputArgument::IS_ARRAY, 'Drush command')
      ->addUsage('<site>.<env> -- <command>')
      ->addUsage('myapp.dev -- uli 1')
      ->addUsage('myapp.dev -- status --fields=db-status');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
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
