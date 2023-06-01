<?php

namespace Acquia\Cli\Command\Pull;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PullDatabaseCommand extends PullCommandBase {

  /**
   * @var string
   */
  protected static $defaultName = 'pull:database';

  protected function configure(): void {
    $this->setDescription('Import database backup from a Cloud Platform environment')
      ->setHelp('This uses the latest available database backup, which may be up to 24 hours old. If no backup exists, one will be created.')
      ->setAliases(['pull:db'])
      ->acceptEnvironmentId()
      ->acceptSite()
      ->addOption('no-scripts', NULL, InputOption::VALUE_NONE,
        'Do not run any additional scripts after the database is pulled. E.g., drush cache-rebuild, drush sql-sanitize, etc.')
      ->addOption('on-demand', NULL, InputOption::VALUE_NONE,
        'Force creation of an on-demand backup. This takes much longer than using an existing backup (when one is available)')
      ->addOption('no-import', NULL, InputOption::VALUE_NONE,
      'Download the backup but do not import it (implies --no-scripts)')
      ->addOption('multiple-dbs', NULL, InputOption::VALUE_NONE,
        'Download multiple dbs. Defaults to FALSE.')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    parent::execute($input, $output);
    $noScripts = $input->hasOption('no-scripts') && $input->getOption('no-scripts');
    $onDemand = $input->hasOption('on-demand') && $input->getOption('on-demand');
    $noImport = $input->hasOption('no-import') && $input->getOption('no-import');
    $multipleDbs = $input->hasOption('multiple-dbs') && $input->getOption('multiple-dbs');
    // $noImport implies $noScripts.
    $noScripts = $noImport || $noScripts;
    $this->pullDatabase($input, $output, $onDemand, $noImport, $multipleDbs);
    if (!$noScripts) {
      $this->runDrushCacheClear($this->getOutputCallback($output, $this->checklist));
      $this->runDrushSqlSanitize($this->getOutputCallback($output, $this->checklist));
    }

    return Command::SUCCESS;
  }

}
