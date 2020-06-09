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
    $this->setAliases(['drush'])
      ->setDescription('Run a Drush command remotely on a application\'s environment')
      ->addArgument('alias', InputArgument::REQUIRED, 'Alias for site & environment in the format `app-name.env`')
      ->addArgument('drush_command', InputArgument::IS_ARRAY, 'Drush command')
      ->addUsage('<site>.<env> -- <command>')
      ->addUsage('mysite.dev -- uli 1')
      ->addUsage('mysite.dev -- status --fields=db-status');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $alias = $this->validateAlias($input->getArgument('alias'));
    $environment = $this->getEnvironmentFromAliasArg($alias);

    $acli_arguments = $input->getArguments();
    $drush_command_arguments = [
      "cd /var/www/html/{$alias}/docroot; ",
      'drush',
      implode(' ', $acli_arguments['drush_command']),
    ];

    return $this->sshHelper->executeCommand($environment, $drush_command_arguments)->getExitCode();
  }

}
