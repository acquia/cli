<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\SelfUpdate\Strategy\GithubStrategy;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Exception;
use Humbug\SelfUpdate\Updater;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpdateCommand.
 */
class UpdateCommand extends CommandBase {

  protected static $defaultName = 'self-update';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('update to the latest version')
      ->setAliases(['update'])
      ->setHidden(AcquiaDrupalEnvironmentDetector::isAhIdeEnv())
      ->addOption('allow-unstable', NULL, InputOption::VALUE_NONE, 'Allow unstable (e.g., alpha, beta, etc.) releases to be downloaded');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if (!$this->updateHelper->getPharPath()) {
      throw new RuntimeException('update only works when running the phar version of ' . $this->getApplication()->getName() . '.');
    }

    $allow_unstable = $input->getOption('allow-unstable') !== FALSE;
    $updater = $this->updateHelper->getUpdater($input, $output, $this->getApplication(), $allow_unstable);
    try {
      $result = $updater->update();
      if ($result) {
        $new = $updater->getNewVersion();
        $old = $updater->getOldVersion();
        $output->writeln("<info>Updated from $old to $new</info>");
      }
      else {
        $output->writeln('<comment>No update needed.</comment>');
      }
      return 0;
    } catch (Exception $e) {
      $output->writeln("<error>{$e->getMessage()}</error>");
      return 1;
    }
  }

}
