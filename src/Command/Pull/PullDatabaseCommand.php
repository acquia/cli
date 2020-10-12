<?php

namespace Acquia\Cli\Command\Pull;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PullFilesCommand.
 */
class PullDatabaseCommand extends PullCommandBase {

  protected static $defaultName = 'pull:database';

  /**
   * @var string
   */
  protected $dir;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Copy database from a Cloud Platform environment')
      ->setAliases(['pull:db'])
      ->addOption('cloud-env-uuid', 'from', InputOption::VALUE_REQUIRED,
        'The UUID of the associated Cloud Platform source environment')
      ->addOption('no-scripts', NULL, InputOption::VALUE_NONE);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->pullDatabase($input, $output);
    if (!$input->getOption('no-scripts')) {
      $this->runDrushScripts($this->getOutputCallback($output, $this->checklist));
    }

    return 0;
  }

}
