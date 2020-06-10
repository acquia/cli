<?php
/**
 * This class is partially patterned after Composer's self-update.
 */

namespace Acquia\Cli\SelfUpdate\Strategy;

use Acquia\Cli\Exception\AcquiaCliException;
use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\JsonParsingException;
use Humbug\SelfUpdate\Strategy\StrategyInterface;
use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\VersionParser;

class GithubStrategy extends \Humbug\SelfUpdate\Strategy\GithubStrategy implements StrategyInterface
{
  const API_URL = 'https://api.github.com/repos/%s/releases';

  /**
   * @var string
   */
  private $remoteVersion;

  /**
   * @var string
   */
  private $remoteUrl;

  /**
   * Retrieve the current version available remotely.
   *
   * @param Updater $updater
   *
   * @return string|bool
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function getCurrentRemoteVersion(Updater $updater) {
    /** Switch remote request errors to HttpRequestExceptions */
    set_error_handler([$updater, 'throwHttpRequestException']);
    $packageUrl = $this->getApiUrl();
    $context = $this->getCurlContext();
    $releases = json_decode(humbug_get_contents($packageUrl, FALSE, $context), TRUE);
    restore_error_handler();

    if (NULL === $releases || json_last_error() !== JSON_ERROR_NONE) {
      throw new JsonParsingException(
        'Error parsing JSON package data'
        . (function_exists('json_last_error_msg') ? ': ' . json_last_error_msg() : '')
      );
    }

    // Remove any version that does not have an attached phar file.
    foreach ($releases as $key => $release) {
      if (!$this->getReleasePharAsset($release)) {
        unset($release[$key]);
      }
    }

    $versions = array_column($releases, 'tag_name');
    $versionParser = new VersionParser($versions);
    if ($this->getStability() === self::STABLE) {
      $this->remoteVersion = $versionParser->getMostRecentStable();
    } elseif ($this->getStability() === self::UNSTABLE) {
      $this->remoteVersion = $versionParser->getMostRecentUnstable();
    } else {
      $this->remoteVersion = $versionParser->getMostRecentAll();
    }

    if (!empty($this->remoteVersion)) {
      $release_key = array_search($this->remoteVersion, $versions, TRUE);
      $phar_asset = $this->getReleasePharAsset($releases[$release_key]);
      $this->remoteUrl = $this->getDownloadUrl($phar_asset);
    }

    return $this->remoteVersion;
  }

  /**
   * @return string
   */
  protected function getApiUrl(): string {
    return sprintf(self::API_URL, $this->getPackageName());
  }

  /**
   * @param array $asset
   *
   * @return string
   */
  protected function getDownloadUrl(array $asset): string {
    return $asset['browser_download_url'];
  }

  protected function getReleasePharAsset(array $release) {
    foreach ($release['assets'] as $key => $asset) {
      if ($asset['name'] === 'acli.phar') {
        return $asset;
      }
    }
    return NULL;
  }

  /**
   * Download the remote Phar file.
   *
   * @param Updater $updater
   * @return void
   */
  public function download(Updater $updater): void {
    /** Switch remote request errors to HttpRequestExceptions */
    set_error_handler([$updater, 'throwHttpRequestException']);
    $context = $this->getCurlContext();
    $result = humbug_get_contents($this->remoteUrl, FALSE, $context);
    restore_error_handler();
    if (FALSE === $result) {
      throw new HttpRequestException(sprintf(
        'Request to URL failed: %s', $this->remoteUrl
      ));
    }

    file_put_contents($updater->getTempPharFile(), $result);
  }

  /**
   * GitHub requires a user agent to be set for all requests to their API.
   *
   * @return resource
   */
  protected function getCurlContext() {
    $opts = [
      'http' => [
        'method' => 'GET',
        'header' => [
          'User-Agent: ' . $this->getPackageName()
        ]
      ]
    ];

    return stream_context_create($opts);
  }

}
