<?php

namespace Acquia\Cli\Command\Pull;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PullScriptsCommand.
 */
class PullScriptsCommand extends PullCommandBase {

  protected static $defaultName = 'pull:run-scripts';

  /**
   * @var string
   */
  protected $dir;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Execute post pull scripts')
      ->addArgument('dir', InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed')
      ->addOption('cloud-env-uuid', 'from', InputOption::VALUE_REQUIRED,
        'The UUID of the associated Cloud Platform source environment');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->executeAllScripts($input, $this->getOutputCallback($output, $this->checklist));

    return 0;
  }

}
