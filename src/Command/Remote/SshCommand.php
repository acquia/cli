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

  protected static $defaultName = 'remote:ssh';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Open a new SSH session to a Cloud Platform environment')
      ->setAliases(['ssh'])
      ->addArgument('alias', InputArgument::REQUIRED, 'Alias for application & environment in the format `app-name.env`')
      ->addArgument('ssh_command', InputArgument::OPTIONAL, 'Command to run via SSH (opens a shell by default)')
      ->addUsage("<app>.<env>");
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
    $arguments = $input->getArguments();
    array_shift($arguments);
    if (empty($arguments['ssh_command'])) {
      $arguments['ssh_command'] = 'cd /var/www/html/' . $alias . '; exec $SHELL -l';
    }
    return $this->sshHelper->executeCommand($environment, $arguments, TRUE, NULL)->getExitCode();
  }

}
