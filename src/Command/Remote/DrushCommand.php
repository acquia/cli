<?php

namespace Acquia\Ads\Command\Remote;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DrushCommand
 * A command to proxy Drush commands on an environment using SSH
 * @package Acquia\Ads\Commands\Remote
 */
class DrushCommand extends SSHBaseCommand
{
    /**
     * @inheritdoc
     */
    protected $command = 'drush';

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('remote:drush')
            ->setDescription('Runs a Drush command remotely on a application\'s environment.')
            ->addArgument('site_env', InputArgument::REQUIRED, 'Site & environment in the format `site-name.env`')
            ->addArgument('drush_command', InputArgument::REQUIRED, 'Drush command')
            ->addUsage(" <site>.<env> -- <command> Runs the Drush command <command> remotely on <site>'s <env> environment.")
            ->addUsage("@usage <site>.<env> --progress -- <command> Runs a Drush command with a progress bar");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // @todo Get a list of all sites that user can access, run $this->setSites();
        $this->prepareEnvironment($input->getArgument('site_env'));
        return $this->executeCommand($input->getArgument('drush_command'));
    }
}
