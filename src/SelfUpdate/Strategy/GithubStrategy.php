<?php
/**
 * Humbug
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/phar-updater/blob/master/LICENSE New BSD License
 *
 * This class is partially patterned after Composer's self-update.
 */

namespace Acquia\Cli\SelfUpdate\Strategy;

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
   * @return string|bool
   */
  public function getCurrentRemoteVersion(Updater $updater) {
    /** Switch remote request errors to HttpRequestExceptions */
    set_error_handler([$updater, 'throwHttpRequestException']);
    $packageUrl = $this->getApiUrl();
    $package = json_decode(humbug_get_contents($packageUrl), TRUE);
    restore_error_handler();

    if (NULL === $package || json_last_error() !== JSON_ERROR_NONE) {
      throw new JsonParsingException(
        'Error parsing JSON package data'
        . (function_exists('json_last_error_msg') ? ': ' . json_last_error_msg() : '')
      );
    }

    $versions = array_keys($package['packages'][$this->getPackageName()]);
    $versionParser = new VersionParser($versions);
    if ($this->getStability() === self::STABLE) {
      $this->remoteVersion = $versionParser->getMostRecentStable();
    } elseif ($this->getStability() === self::UNSTABLE) {
      $this->remoteVersion = $versionParser->getMostRecentUnstable();
    } else {
      $this->remoteVersion = $versionParser->getMostRecentAll();
    }

    /**
     * Setup remote URL if there's an actual version to download
     */
    if (!empty($this->remoteVersion)) {
      $this->remoteUrl = $this->getDownloadUrl($package);
    }

    return $this->remoteVersion;
  }

  protected function getApiUrl()
  {
    return sprintf(self::API_URL, $this->getPackageName());
  }

  protected function getDownloadUrl(array $package) {
    $baseUrl = preg_replace(
      '{\.git$}',
      '',
      $package['packages'][$this->getPackageName()][$this->remoteVersion]['source']['url']
    );
    $downloadUrl = sprintf(
      '%s/releases/download/%s/%s',
      $baseUrl,
      $this->remoteVersion,
      $this->getPharName()
    );
    return $downloadUrl;
  }

}
