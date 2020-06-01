<?php

namespace Acquia\Cli\Command;

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Phar;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpdateCommand.
 */
class UpdateCommand extends CommandBase {

  /**
   * @var string
   */
  protected $gitHubRepository;

  /**
   * @var string
   */
  protected $applicationName;

  /**
   * @var string
   */
  protected $pharFilepath;

  /**
   * @var string
   */
  protected $pharFilename;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('self-update')->setDescription('Update to the latest version of Acquia CLI');
  }

  /**
   * @return bool
   */
  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if (empty(Phar::running())) {
      throw new RuntimeException('update only works when running the phar version of ' . $this->getApplication()
          ->getName() . '.');
    }

    $updater = new Updater(NULL, FALSE, Updater::STRATEGY_GITHUB);
    $updater->getStrategy()->setStability(GithubStrategy::STABLE);
    $updater->getStrategy()->setPackageName('acquia/cli');
    $updater->getStrategy()->setPharName('acli');
    $updater->getStrategy()->setCurrentLocalVersion($this->getApplication()->getVersion());
    try {
      $result = $updater->update();
      if ($result) {
        $new = $updater->getNewVersion();
        $old = $updater->getOldVersion();
        $output->writeln("<info>Updated from $old to $new</info>");
      } else {
        $output->writeln('<comment>No update needed.</comment>');
      }
      return 0;
    } catch (\Exception $e) {
      $output->writeln("<error>{$e->getMessage()}</error>");
      return 1;
    }
  }

}
