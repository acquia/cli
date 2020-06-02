<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Phar;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpdateCommand.
 */
class UpdateCommand extends CommandBase {

  /** @var string */
  protected $pharPath;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('self-update')
      ->addOption('allow-unstable', NULL, InputOption::VALUE_NONE, 'Allow unstable (e.g., alpha, beta, etc.) releases to be downloaded')
      ->setDescription('update to the latest version');
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
    if (!$this->getPharPath()) {
      throw new RuntimeException('update only works when running the phar version of ' . $this->getApplication()
          ->getName() . '.');
    }

    $updater = new Updater($this->getPharPath(), FALSE);
    $updater->setStrategyObject(new GithubStrategy());
    $stability = $input->getOption('allow-unstable') !== FALSE ? GithubStrategy::UNSTABLE : GithubStrategy::STABLE;
    $updater->getStrategy()->setStability($stability);
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

  /**
   * @return string
   */
  public function getPharPath(): string {
    if (!isset($this->pharPath)) {
      $this->pharPath = Phar::running(TRUE);
    }
    return $this->pharPath;
  }

  /**
   * @param string $pharPath
   */
  public function setPharPath(string $pharPath): void {
    $this->pharPath = $pharPath;
  }

}
