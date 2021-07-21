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
    $this->setDescription('Import database backup from a Cloud Platform environment')
      ->setHelp('This uses the latest available database backup, which may be up to 24 hours old. If no backup exists, one will be created.')
      ->setAliases(['pull:db'])
      ->acceptEnvironmentId()
      ->acceptSite()
      ->addOption('no-scripts', NULL, InputOption::VALUE_NONE,
        'Do not run any additional scripts after the database is pulled. E.g., drush cache-rebuild, drush sql-sanitize, etc.')
      ->addOption('on-demand', 'od', InputOption::VALUE_NONE,
        'Force creation of an on-demand backup. This takes much longer than using an existing backup (when one is available)')
      ->addOption('no-import', NULL, InputOption::VALUE_NONE,
      'Download the backup but do not import it (implies --no-scripts)')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
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
    $no_scripts = $input->hasOption('no-scripts') && $input->getOption('no-scripts');
    $on_demand = $input->hasOption('on-demand') && $input->getOption('on-demand');
    $no_import = $input->hasOption('no-import') && $input->getOption('no-import');
    // $no_import implies $no_scripts.
    $no_scripts = $no_import || $no_scripts;
    $this->pullDatabase($input, $output, $on_demand, $no_import);
    if (!$no_scripts) {
      $this->runDrushCacheClear($this->getOutputCallback($output, $this->checklist));
    }

    return 0;
  }

}
