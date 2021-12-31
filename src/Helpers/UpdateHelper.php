<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Application;
use Composer\Semver\VersionParser;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Class LocalMachineHelper.
 *
 * A helper for executing commands on the local client. A wrapper for 'exec' and 'passthru'.
 *
 * @package Acquia\Cli\Helpers
 */
class UpdateHelper {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * @var \Acquia\Cli\Application
   */
  private Application $application;

  /**
   * @var \GuzzleHttp\Client
   */
  private Client $updateClient;

  /**
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(
    LoggerInterface $logger,
    Application $application
  ) {
    $this->logger = $logger;
    $this->application = $application;
  }

  /**
   * Check if an update is available.
   *
   * @throws \Exception|\GuzzleHttp\Exception\GuzzleException
   * @todo unify with consolidation/self-update and support unstable channels
   */
  public function hasUpdate() {
    $client = $this->getUpdateClient();
    $response = $client->get('https://api.github.com/repos/acquia/cli/releases');
    if ($response->getStatusCode() !== 200) {
      $this->logger->debug('Encountered ' . $response->getStatusCode() . ' error when attempting to check for new ACLI releases on GitHub: ' . $response->getReasonPhrase());
      return FALSE;
    }

    $releases = json_decode($response->getBody());
    if (!isset($releases[0])) {
      $this->logger->debug('No releases found at GitHub repository acquia/cli');
      return FALSE;
    }

    /**
     * @var $version string
     */
    $version = $releases[0]->tag_name;
    $versionStability = VersionParser::parseStability($version);
    $versionIsNewer = version_compare($version, $this->application->getVersion());
    if ($versionStability === 'stable' && $versionIsNewer) {
      return $version;
    }

    return FALSE;
  }

  /**
   * @param \GuzzleHttp\Client $client
   */
  public function setUpdateClient(Client $client): void {
    $this->updateClient = $client;
  }

  /**
   * @return \GuzzleHttp\Client
   */
  public function getUpdateClient(): Client {
    if (!isset($this->updateClient)) {
      $stack = HandlerStack::create();
      $stack->push(new CacheMiddleware(
        new PrivateCacheStrategy(
          new Psr6CacheStorage(
            new FilesystemAdapter('acli')
          )
        )
      ),
        'cache');
      $client = new Client(['handler' => $stack]);
      $this->setUpdateClient($client);
    }
    return $this->updateClient;
  }

}
