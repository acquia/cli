<?php

namespace Acquia\Cli\Command\Pull;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PullDatabaseCommand.
 */
class PullDatabaseCommand extends PullCommandBase {

  protected static $defaultName = 'pull:database';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Import latest database backup from a Cloud Platform environment')
      ->setHelp('This uses the latest available database backup, which may be up to 24 hours old. You can generate an on-demand backup using api:environments:database-backup-create.')
      ->setAliases(['pull:db'])
      ->addOption('no-scripts', NULL, InputOption::VALUE_NONE,
        'Do not run any additional scripts after the database is pulled. E.g., drush cache-rebuild, drush sql-sanitize, etc.')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
    $this->acceptEnvironmentId();
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    parent::execute($input, $output);
    $this->pullDatabase($input, $output);
    if (!$input->getOption('no-scripts')) {
      $this->runDrushCacheClear($this->getOutputCallback($output, $this->checklist));
    }

    return 0;
  }

}
