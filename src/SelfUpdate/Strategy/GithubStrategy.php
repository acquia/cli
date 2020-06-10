<?php

namespace Acquia\Cli\SelfUpdate\Strategy;

use GuzzleHttp\Client;
use Humbug\SelfUpdate\Exception\HttpRequestException;
use Humbug\SelfUpdate\Exception\JsonParsingException;
use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\VersionParser;

class GithubStrategy extends \Humbug\SelfUpdate\Strategy\GithubStrategy {
  public const API_URL = 'https://api.github.com/repos/%s/releases';

  /**
   * @var string
   */
  private $remoteVersion;

  /**
   * @var string
   */
  private $remoteUrl;

  /**
   * @var Client
   */
  private $client;

  /**
   * Retrieve the current version available remotely.
   *
   * @param Updater $updater
   *
   * @return string|bool
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getCurrentRemoteVersion(Updater $updater) {
    $packageUrl = $this->getApiUrl();
    $client = $this->getClient();
    $response = $client->request('GET', $packageUrl, [
      'headers' => ['User-Agent' => $this->getPackageName()]
    ]);
    $contents = $response->getBody()->getContents();
    $releases = json_decode($contents, TRUE);

    if (NULL === $releases || json_last_error() !== JSON_ERROR_NONE) {
      throw new JsonParsingException(
        'Error parsing JSON package data'
        . (function_exists('json_last_error_msg') ? ': ' . json_last_error_msg() : '')
      );
    }

    // Remove any version that does not have an attached phar file.
    foreach ($releases as $key => $release) {
      if (!$this->getReleasePharAsset($release)) {
        unset($releases[$key]);
      }
    }
    // Re-key the array.
    $releases = array_values($releases);

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
   * @param Client $client
   */
  public function setClient($client): void {
    $this->client = $client;
  }

  /**
   * @return Client
   */
  public function getClient(): Client {
    return $this->client;
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
   *
   * @return void
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function download(Updater $updater): void {
    $response = $this->getClient()->request('GET', $this->remoteUrl, [
      'headers' => ['User-Agent' => $this->getPackageName()]
    ]);
    $response_contents = $response->getBody()->getContents();
    file_put_contents($updater->getTempPharFile(), $response_contents);
  }

}
