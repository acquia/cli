<?php

namespace Acquia\Ads\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RefreshCommand.
 */
class RefreshCommand extends CommandBase
{

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('refresh')
          ->setDescription('Copy code, database, and files from an Acquia Cloud environment')
          ->addOption('from', null, InputOption::VALUE_NONE, 'The source environment')
          ->addOption('no-code', null, InputOption::VALUE_NONE, 'Do not refresh code from remote repository')
          ->addOption('no-files', null, InputOption::VALUE_NONE, 'Do not refresh files')
          ->addOption('no-databases', null, InputOption::VALUE_NONE, 'Do not refresh databases')
          ->addOption('no-scripts', null, InputOption::VALUE_NONE,
            'Do not run any additional scripts after code and database are copied. E.g., composer install , drush cache-rebuild, etc.')
          ->addOption('code', null, InputOption::VALUE_NONE, 'Copy only code from remote repository')
          ->addOption('files', null, InputOption::VALUE_NONE, 'Copy only files from remote Acquia Cloud environment')
          ->addOption('databases', null, InputOption::VALUE_NONE,
            'Copy only databases from remote Acquia Cloud environment')
          ->addOption('scripts', null, InputOption::VALUE_NONE, 'Only execute additional scripts');
        // @todo Add option to allow specifying source environment.
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output->writeln('<comment>This is a command stub. The command logic has not been written yet.');

        // Choose remote environment.
        // Git clone if no local repo found.
        // Else Pull code IF not dirty. Fetch and then checkout.
        // Copy databases.
        // Copy files.
        // Composer install.
        // Drush sanitize.
        // Drush rebuild caches.

        return 0;
    }
}
