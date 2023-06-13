<?php

declare(strict_types=1);

namespace AcquiaMigrate\SourceSite;

/**
 * Inspects a Drupal 7 site.
 */
final class Drupal7SiteInspector extends SiteInspectorBase {

  /**
   * The path to Drupal site root.
   *
   * @var string
   */
  protected $root;

  /**
   * The host name to use in order to resolve the appropriate settings.php.
   *
   * @var string
   */
  protected $uri;

  /**
   * Drupal7SiteInspector constructor.
   *
   * @param string $drupal_root
   *   The path to Drupal site root.
   * @param string $uri
   *   (optional) The host name to use in order to resolve the appropriate
   *   settings.php directory. Defaults to 'default'.
   */
  public function __construct(string $drupal_root, string $uri = 'default') {
    $this->root = $drupal_root;
    $this->uri = $uri;
  }

  /**
   * {@inheritDoc}
   *
   * Uses drush to get all known extensions on the context Drupal 7 site.
   */
  protected function readExtensions() : array {
    $this->bootstrap();
    $enabled = system_list('module_enabled');
    // Special case to remove 'standard' from the module's list.
    unset($enabled['standard']);
    $modules = array_values(array_map(function (string $name) use ($enabled) {
      return (object) [
        'name' => $name,
        'status' => TRUE,
        'type' => 'module',
        'humanName' => $enabled[$name]->info['name'],
        'version' => $enabled[$name]->info['version'],
      ];
    }, array_keys($enabled)));
    return array_map([Drupal7Extension::class, 'createFromStdClass'], $modules);
  }

  /**
   * {@inheritDoc}
   */
  public function getPublicFilePath() : string {
    $this->bootstrap();
    // @see https://git.drupalcode.org/project/drupal/-/blob/7.x/includes/stream_wrappers.inc#L919
    return variable_get('file_public_path', conf_path() . '/files');
  }

  /**
   * {@inheritDoc}
   */
  public function getPrivateFilePath() : ?string {
    $this->bootstrap();
    return variable_get('file_private_path', NULL);
  }

  /**
   * Bootstraps the inspected Drupal site.
   */
  protected function bootstrap() {
    static $bootstrapped;
    if ($bootstrapped) {
      return;
    }
    $previous_directory = getcwd();
    chdir($this->root);
    if (!defined('DRUPAL_ROOT')) {
      define('DRUPAL_ROOT', $this->root);
    }
    $_SERVER['HTTP_HOST'] = $this->uri;
    $_SERVER['REQUEST_URI'] = $this->uri . '/';
    $_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'] . 'index.php';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_METHOD']  = 'GET';
    $_SERVER['SERVER_SOFTWARE'] = NULL;
    $_SERVER['HTTP_USER_AGENT'] = 'console';
    $_SERVER['SCRIPT_FILENAME'] = DRUPAL_ROOT . '/index.php';
    require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    drupal_bootstrap(DRUPAL_BOOTSTRAP_VARIABLES);
    chdir($previous_directory);
    $bootstrapped = TRUE;
  }

}
