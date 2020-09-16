<?php

namespace Acquia\Cli\SelfUpdate\Strategy;

use GuzzleHttp\Client;
use Humbug\SelfUpdate\Exception\JsonParsingException;
use Humbug\SelfUpdate\Updater;
use Humbug\SelfUpdate\VersionParser;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

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
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private $output;

  /**
   * @var array
   */
  private $asset;

  public function __construct(OutputInterface $output) {
    $this->output = $output;
  }

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
      $this->asset = $phar_asset;
    }

    return $this->remoteVersion;
  }

  /**
   * @return \GuzzleHttp\Client
   */
  public function getClient(): \GuzzleHttp\Client {
    return $this->client;
  }

  /**
   * @param \GuzzleHttp\Client $client
   */
  public function setClient(\GuzzleHttp\Client $client): void {
    $this->client = $client;
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
    $this->output->writeln('Downloading Acquia CLI ' . $this->remoteVersion);
    $progress = NULL;
    $client = $this->getClient();
    $output = $this->output;
    $asset_size = $this->asset['size'];
    $response = $client->request('GET', $this->remoteUrl, [
      'headers' => ['User-Agent' => $this->getPackageName()],
      'progress' => static function ($total_bytes, $downloaded_bytes, $upload_total, $uploaded_bytes) use (&$progress, $output, $asset_size) {
        self::displayDownloadProgress($asset_size, $downloaded_bytes, $progress, $output);
      },
    ]);
    $response_contents = $response->getBody()->getContents();
    file_put_contents($updater->getTempPharFile(), $response_contents);
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
      $progress->setOverwrite(TRUE);
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
