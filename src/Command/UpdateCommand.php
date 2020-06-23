<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\SelfUpdate\Strategy\GithubStrategy;
use Exception;
use GuzzleHttp\Client;
use Humbug\SelfUpdate\Updater;
use Phar;
use RuntimeException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

/**
 * Class UpdateCommand.
 */
class UpdateCommand extends CommandBase {

  protected static $defaultName = 'self-update';

  /** @var string */
  protected $pharPath;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('update to the latest version')
      ->setAliases(['update'])
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
    if (!$this->getPharPath()) {
      throw new RuntimeException('update only works when running the phar version of ' . $this->getApplication()->getName() . '.');
    }

    $updater = new Updater($this->getPharPath(), FALSE);
    $strategy = new GithubStrategy($output);
    $updater->setStrategyObject($strategy);
    $updater->getStrategy()->setClient($this->getClient());
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
        // This is a bit of a hack. But, we exit prematurely to avoid any type of error based on post replace
        // code execution. @see https://github.com/acquia/cli/issues/169
        exit(0);
      }

      $output->writeln('<comment>No update needed.</comment>');
    } catch (Exception $e) {
      $output->writeln("<error>{$e->getMessage()}</error>");
      return 1;
    }
  }

  /**
   * @param \GuzzleHttp\Client $client
   */
  public function setClient($client): void {
    $this->client = $client;
  }

  /**
   * @return \GuzzleHttp\Client
   */
  public function getClient(): Client {
    if (!isset($this->client)) {
      $client = $this->createDefaultClient();
      $this->setClient($client);
    }
    return $this->client;
  }

  /**
   * @return string
   */
  public function getPharPath(): string {
    if (!isset($this->pharPath)) {
      $this->setPharPath(Phar::running(FALSE));
    }
    return $this->pharPath;
  }

  /**
   * @param string $pharPath
   */
  public function setPharPath(string $pharPath): void {
    $this->pharPath = Path::canonicalize($pharPath);
  }

  /**
   * @return \GuzzleHttp\Client
   */
  protected function createDefaultClient(): Client {
    $progress = NULL;
    $output = $this->output;
    $options = [
      'progress' => function ($total_bytes, $downloaded_bytes) use ($progress, $output) {
        self::displayDownloadProgress($total_bytes, $downloaded_bytes, $progress, $output);
      },
    ];

    return new Client($options);
  }

  /**
   * @param $total_bytes
   * @param $downloaded_bytes
   * @param $progress
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public static function displayDownloadProgress($total_bytes, $downloaded_bytes, &$progress, OutputInterface $output): void {
    if ($total_bytes > 0 && is_null($progress)) {
      $progress = new ProgressBar($output, $total_bytes);
      $progress->setProgressCharacter('💧');
      $progress->start();
    }

    if (!is_null($progress)) {
      if ($total_bytes === $downloaded_bytes) {
        $progress->finish();
        return;
      }
      $progress->setProgress($downloaded_bytes);
    }
  }

}
