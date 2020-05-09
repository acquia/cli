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
class SshCommand extends SshBaseCommand {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('remote:ssh')
      ->setDescription('Opens a new SSH connection to an Acquia Cloud environment.')
      ->addArgument('site_env', InputArgument::REQUIRED, 'Site & environment in the format `site-name.env`')
      ->addUsage(" <site>.<env> -- <command> Runs the Drush command <command> remotely on <site>'s <env> environment.");
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $alias = $this->validateAlias($input->getArgument('alias'));
    $this->environment = $this->getEnvironmentFromAliasArg($alias);

    $arguments = $input->getArguments();
    array_shift($arguments);
    array_unshift($arguments, 'bash', '-l');

    return $this->executeCommand($arguments);
  }

}
