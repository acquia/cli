<?php

namespace Acquia\Ads\Exec;

use drupol\phposinfo\OsInfo;
use drupol\phposinfo\Enum\Family;
use drupol\phposinfo\Enum\Os;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Process\Process;

trait ExecTrait
{
    use LoggerAwareTrait;

    /**
     * Starts a background browser/tab for the current site or a specified URL.
     *
     * Uses a non-blocking Process call, so Drush execution will continue.
     *
     * @param $uri
     *   Optional URI or site path to open in browser. If omitted, or if a site path
     *   is specified, the current site home page uri will be prepended if the site's
     *   hostname resolves.
     * @param int $sleep
     * @param bool $port
     * @param bool $browser
     * @return bool
     *   TRUE if browser was opened. FALSE if browser was disabled by the user or a
     *   default browser could not be found.
     */
    public function startBrowser($uri = null, $sleep = 0, $port = false, $browser = true): bool
    {
        if ($browser) {
            // We can only open a browser if we have a DISPLAY environment variable on
            // POSIX or are running Windows or OS X.
            if (!getenv('DISPLAY') && !OsInfo::isWindows() && !OsInfo::isApple()) {
                $this->logger->info('No graphical display appears to be available, not starting browser.');
                return false;
            }
            $host = parse_url($uri, PHP_URL_HOST);
            if (!$host) {
                // Build a URI for the current site, if we were passed a path.
                $site = $this->uri;
                $host = parse_url($site, PHP_URL_HOST);
                $uri = $site . '/' . ltrim($uri, '/');
            }
            // Validate that the host part of the URL resolves, so we don't attempt to
            // open the browser for http://default or similar invalid hosts.
            $hosterror = (gethostbynamel($host) === false);
            $iperror = (ip2long($host) && gethostbyaddr($host) == $host);
            if ($hosterror || $iperror) {
                $this->logger->warning('!host does not appear to be a resolvable hostname or IP, not starting browser. You may need to use the --uri option in your command or site alias to indicate the correct URL of this site.', ['!host' => $host]);
                return false;
            }
            if ($port) {
                $uri = str_replace($host, "localhost:$port", $uri);
            }
            if ($browser === true) {
                // See if we can find an OS helper to open URLs in default browser.
                if ($this->programExists('xdg-open')) {
                    $browser = 'xdg-open';
                } else if ($this->programExists('open')) {
                    $browser = 'open';
                } else if ($this->programExists('start')) {
                    $browser = 'start';
                } else {
                    // Can't find a valid browser.
                    $browser = false;
                }
            }

            if ($browser) {
                $this->logger->info('Opening browser !browser at !uri', ['!browser' => $browser, '!uri' => $uri]);
                $args = [];

                if ($sleep) {
                    $args = ['sleep', $sleep, '&&'];
                }
                // @todo We implode because quoting is messing up the sleep.
                $process = new Process(array_merge($args, [$browser, $uri]));
                $process->run();

                return true;
            }
        }
        return false;
    }

    /*
     * Determine if program exists on user's PATH.
     *
     * @return bool
     *   True if program exists on PATH.
     */
    public function programExists($program): bool
    {
        $command = OsInfo::isWindows() ? ['where', $program] : ['command', '-v', $program];
        $process = new Process($command);
        $process->run();


        if (!$process->isSuccessful()) {
            $this->logger->debug($process->getErrorOutput());
        }
        return $process->isSuccessful();
    }
}
