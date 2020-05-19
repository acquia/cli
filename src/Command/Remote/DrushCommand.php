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

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('remote:drush')
      ->setAliases(['drush'])
      ->setDescription('Runs a Drush command remotely on a application\'s environment.')
      ->addArgument('alias', InputArgument::REQUIRED, 'Alias for site & environment in the format `app-name.env`')
      ->addArgument('drush_command', InputArgument::REQUIRED, 'Drush command')
      ->addUsage(" <site>.<env> -- <command> Runs the Drush command <command> remotely on <site>'s <env> Cloud environment.")
      ->addUsage('@usage <site>.<env> --progress -- <command> Runs a Drush command with a progress bar');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $alias = $this->validateAlias($input->getArgument('alias'));
    $environment = $this->getEnvironmentFromAliasArg($alias);

    $arguments = $input->getArguments();
    // Remove 'remote:drush' command from array.
    array_shift($arguments);
    // Add command to array.
    array_unshift($arguments, "cd /var/www/html/{$alias}/docroot; ", 'drush');

    return $this->getApplication()->getSshHelper()->executeCommand($environment, $arguments)->getExitCode();
  }

}
