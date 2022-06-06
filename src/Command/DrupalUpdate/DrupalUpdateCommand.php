<?php


namespace Acquia\Cli\Command\DrupalUpdate;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalUpdateCommand extends  CommandBase
{
  protected static $defaultName = 'd7-update';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Drupal 7 update codebase.')
            ->addOption('docroot-path', NULL, InputOption::VALUE_REQUIRED, 'Drupal docroot path.')
            ->setHidden(TRUE);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->io->success('Start D7 update checking.');
    $path = $input->getOption('docroot-path');
    if(!isset($path)){
      define('DRUPAL_ROOT', getcwd());
    }else{
      define('DRUPAL_ROOT', $path);
    }

    if(file_exists(DRUPAL_ROOT . '/docroot/includes/bootstrap.inc')){
      include_once DRUPAL_ROOT . '/docroot/includes/bootstrap.inc';
    }
    $updatescript = new UpdateScript();
    // get all .info files
    $updatescript->getInfoFilesList();

    // get all modules, themes, profiles details.
    $updatescript->getPackageDetailInfo();

    // get available security updates of core and contrib.
    $latest_updates = $updatescript->securityUpdateVersion();

    // update and show updated the details & remove the downloaded tar files.
    $updatescript->updateAvailableUpdates($latest_updates);
    return 0;
  }

}
