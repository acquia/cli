<?php

namespace Acquia\Cli\Command\Pull;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PullCodeCommand.
 */
class PullCodeCommand extends PullCommandBase {

  protected static $defaultName = 'pull:code';

  /**
   * @var string
   */
  protected $dir;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Copy code from a Cloud Platform environment')
      ->addArgument('dir', InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed')
      ->addOption('cloud-env-uuid', 'from', InputOption::VALUE_REQUIRED,
        'The UUID of the associated Cloud Platform source environment')
      ->addOption('no-scripts', NULL, InputOption::VALUE_NONE,
        'Do not run any additional scripts after code and database are copied. E.g., composer install , drush cache-rebuild, etc.');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

  }

}
