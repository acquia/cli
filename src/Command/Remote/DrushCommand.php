<?php

namespace Acquia\Ads\Command\Remote;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DrushCommand
 * A command to proxy Drush commands on an environment using SSH.
 *
 * @package Acquia\Ads\Commands\Remote
 */
class DrushCommand extends SSHBaseCommand {
  /**
   * @inheritdoc
   */
  protected $command = 'drush';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('remote:drush')
      ->setAliases(['drush'])
      ->setDescription('Runs a Drush command remotely on a application\'s environment.')
      ->addArgument('site_env', InputArgument::REQUIRED, 'Site & environment in the format `site-name.env`')
      ->addArgument('drush_command', InputArgument::REQUIRED, 'Drush command')
      ->addUsage(" <site>.<env> -- <command> Runs the Drush command <command> remotely on <site>'s <env> Cloud environment.")
      ->addUsage('@usage <site>.<env> --progress -- <command> Runs a Drush command with a progress bar');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Acquia\Ads\Exception\AdsException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // @todo Validate the arg format.
    $site_env = $input->getArgument('site_env');
    $site_env_parts = explode('.', $site_env);
    $drush_site = $site_env_parts[0];
    $drush_env = $site_env_parts[1];

    // @todo Add error handling.
    $this->environment = $this->getEnvFromAlias($drush_site, $drush_env);

    $arguments = $input->getArguments();
    array_shift($arguments);
    array_unshift($arguments, "cd /var/www/html/{$drush_site}.{$drush_env}/docroot; ", 'drush');

    return $this->executeCommand($arguments);
  }

}
