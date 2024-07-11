<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\SourceSite;

use Acquia\Cli\Command\App\NewFromDrupal7Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * Inspects a Drupal 7 site.
 */
final class Drupal7SiteInspector extends SiteInspectorBase
{
    /**
     * The path to Drupal site root.
     */
    protected string $root;

    /**
     * The host name to use in order to resolve the appropriate settings.php.
     */
    public string $uri;

    /**
     * Drupal7SiteInspector constructor.
     *
     * @param string $drupal_root
     *   The path to Drupal site root.
     * @param string $uri
     *   (optional) The host name to use in order to resolve the appropriate
     *   settings.php directory. Defaults to 'default'.
     */
    public function __construct(string $drupal_root, string $uri = 'default')
    {
        $this->root = $drupal_root;
        $this->uri = $uri;
    }

    /**
     * {@inheritDoc}
     *
     * Uses drush to get all known extensions on the context Drupal 7 site.
     */
    protected function readExtensions(): array
    {
        $this->bootstrap();
        // @phpstan-ignore-next-line
        $enabled = system_list('module_enabled');
        // Special case to remove 'standard' from the module's list.
        unset($enabled['standard']);
        $modules = array_values(array_map(function (string $name) use ($enabled) {
          // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            return (object) [
                'name' => $name,
                'status' => true,
                'type' => 'module',
                'humanName' => $enabled[$name]->info['name'],
                'version' => $enabled[$name]->info['version'],
            ];
          // phpcs:enable
        }, array_keys($enabled)));
        return array_map([Drupal7Extension::class, 'createFromStdClass'], $modules);
    }

    public function getPublicFilePath(): string
    {
        $this->bootstrap();
        // @see https://git.drupalcode.org/project/drupal/-/blob/7.x/includes/stream_wrappers.inc#L919
        // @phpstan-ignore-next-line
        return variable_get('file_public_path', conf_path() . '/files');
    }

    public function getPrivateFilePath(): ?string
    {
        $this->bootstrap();
        // @phpstan-ignore-next-line
        return variable_get('file_private_path', null);
    }

    /**
     * Bootstraps the inspected Drupal site.
     */
    protected function bootstrap(): void
    {
        static $bootstrapped;
        if ($bootstrapped) {
            return;
        }
        $previous_directory = getcwd();
        chdir($this->root);
        if (!defined('DRUPAL_ROOT')) {
            define('DRUPAL_ROOT', $this->root);
        }
        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
        $_SERVER['HTTP_HOST'] = $this->uri;
        $_SERVER['REQUEST_URI'] = $this->uri . '/';
        $_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'] . 'index.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['SERVER_SOFTWARE'] = null;
        $_SERVER['HTTP_USER_AGENT'] = 'console';
        $_SERVER['SCRIPT_FILENAME'] = DRUPAL_ROOT . '/index.php';
        // phpcs:enable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
        require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
        // @phpstan-ignore-next-line
        drupal_bootstrap(DRUPAL_BOOTSTRAP_VARIABLES);
        chdir($previous_directory);
        $bootstrapped = true;
    }

    /**
     * Validates the given Drupal 7 application root.
     *
     * @param string $path
     *   The path to validate.
     * @return string
     *   The received Drupal 7 path, if it is valid, without trailing slashes.
     */
    public static function validateDrupal7Root(string $path): string
    {
        $path = rtrim($path, '/');
        if (!file_exists($path)) {
            throw new ValidatorException(sprintf("The path '%s' does not exist. Please enter the absolute path to a Drupal 7 application root.", $path));
        }
        if (!file_exists("$path/index.php")) {
            throw new ValidatorException(sprintf("The '%s' directory does not seem to be the root of a Drupal 7 application. It does not contain a index.php file.", $path));
        }
        if (!file_exists("$path/sites/default/default.settings.php")) {
            throw new ValidatorException(sprintf("The '%s' directory does not seem to be the root of a Drupal 7 application. It does not contain a sites/default/default.settings.php.", $path));
        }
        return $path;
    }

    /**
     * Determines the best URI to use for bootstrapping the source site.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input passed into this Symfony command.
     * @param string $drupal_root
     *   The root of the source site.
     * @return string
     *   A URI string corresponding to an installed site.
     */
    public static function getSiteUri(InputInterface $input, string $drupal_root): string
    {
        // Construct a list of site directories which contain a settings.php file.
        $site_dirs = array_map(function ($path) use ($drupal_root) {
            return substr($path, strlen("$drupal_root/sites/"), -1 * strlen('/settings.php'));
        }, glob("$drupal_root/sites/*/settings.php"));
        // If the --drupal7-uri flag is defined, defer to it and attempt to ensure that it's
        // valid.
        if ($input->getOption('drupal7-uri') !== null) {
            $uri = $input->getOption('drupal7-uri');
            $sites_location = "$drupal_root/sites/sites.php";
            // If there isn't a sites.php file and the URI does not correspond to a
            // site directory, the site will be unable to bootstrap.
            if (!file_exists($sites_location) && !in_array($uri, $site_dirs, true)) {
                throw new \InvalidArgumentException(
                    sprintf('The given --drupal7-uri value does not correspond to an installed sites directory and a sites.php file could not be located.'),
                    NewFromDrupal7Command::ERR_UNRECOGNIZED_HOST
                );
            }
            // Parse the contents of sites.php.
            $sites = [];
            // This will override $sites.
            // @see https://git.drupalcode.org/project/drupal/-/blob/7.x/includes/bootstrap.inc#L563
            include $sites_location;

            // @phpstan-ignore-next-line
            if (!empty($sites)) {
                // If the URI corresponds to a configuration in sites.php, then ensure
                // that the identified directory also has a settings.php file. If it
                // does not, then the site is probably not installed.
                if (isset($sites[$uri])) {
                    if (!in_array($sites[$uri], $site_dirs, true)) {
                        throw new \InvalidArgumentException(
                            sprintf('The given --drupal7-uri value corresponds to a site directory in sites.php, but that directory does not have a settings.php file. This typically means that the site has not been installed.'),
                            NewFromDrupal7Command::ERR_UNRECOGNIZED_HOST
                        );
                    }
                    // The URI is assumed to be valid.
                    return $uri;
                }
                // The given URI doesn't match anything in sites.php.
                throw new \InvalidArgumentException(
                    sprintf('The given --drupal7-uri value does not correspond to any configuration in sites.php.'),
                    NewFromDrupal7Command::ERR_UNRECOGNIZED_HOST
                );
            }

            if (in_array($uri, $site_dirs, true)) {
                return $uri;
            }
        }
        // There was no --drupal7-uri flag specified, so attempt to determine a sane
        // default. If there is only one possible site, use it. If there is more
        // than one, but there is a default directory with a settings.php, use that.
        if (count($site_dirs) === 1) {
            return current($site_dirs);
        } elseif (in_array('default', $site_dirs, true)) {
            return 'default';
        }
        // A URI corresponding to a site directory could not be determined, rather
        // than make a faulty assumption (e.g. use the first found), exit.
        throw new \InvalidArgumentException(
            sprintf('A Drupal 7 installation could not be located.'),
            NewFromDrupal7Command::ERR_INDETERMINATE_SITE
        );
    }
}
