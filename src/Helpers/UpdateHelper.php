<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\SelfUpdate\Strategy\GithubStrategy;
use GuzzleHttp\Client;
use Humbug\SelfUpdate\Updater;
use Phar;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

class UpdateHelper {

  /** @var string */
  protected $pharPath;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

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
      $client = new Client();
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
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @param \Symfony\Component\Console\Application $application
   *
   * @return \Humbug\SelfUpdate\Updater
   */
  public function getUpdater(InputInterface $input, OutputInterface $output, Application $application): Updater {
    $updater = new Updater($this->getPharPath(), FALSE);
    $strategy = new GithubStrategy($output);
    $updater->setStrategyObject($strategy);
    $updater->getStrategy()->setClient($this->getClient());
    $stability = $input->getOption('allow-unstable') !== FALSE ? GithubStrategy::UNSTABLE : GithubStrategy::STABLE;
    $updater->getStrategy()->setStability($stability);
    $updater->getStrategy()->setPackageName('acquia/cli');
    $updater->getStrategy()->setPharName('acli');
    $updater->getStrategy()->setCurrentLocalVersion($application->getVersion());
    return $updater;
  }

}
