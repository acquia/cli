<?php

/**
 * @file
 * Acquia CLI command line front file.
 */

namespace Acquia\Cli;

use Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Filesystem\Filesystem;

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
  die("Could not find autoloader. Run 'composer install' first.\n");
}
require __DIR__ . '/../vendor/autoload.php';

ini_set('memory_limit', -1);
set_time_limit(0);

$input = new ArgvInput();
$env = $input->getParameterOption(['--env', '-e'], $_SERVER['APP_ENV'] ?? 'prod', TRUE);
$debug = (bool) ($_SERVER['APP_DEBUG'] ?? ('prod' !== $env)) && !$input->hasParameterOption('--no-debug', TRUE);

if ($debug) {
  umask(0000);

  // phpcs:disable MySource.Debug.DebugCode.Found
  if (class_exists(Debug::class)) {
    Debug::enable();
    // phpcs:enable
  }
}

$kernel = new Kernel($env, $debug);

// Handle a cache:clear pseudo command. This isn't implemented as a true console
// command because a stale or corrupted cache would render it unusable--
// precisely when it is needed.
if (in_array($input->getFirstArgument(), ['cache:clear', 'cc'])) {
  $filesystem = new Filesystem();
  $cache_dir = $kernel->getCacheDir();
  $filesystem->remove($cache_dir);
  $filesystem->mkdir($cache_dir);
  $filesystem->touch("{$cache_dir}/.gitkeep");
  exit;
}

$kernel->boot();
$container = $kernel->getContainer();
$application = $container->get(Application::class);
$application->setName('Acquia CLI');
$application->setVersion('@package_version@');

// Add command autocompletion.
$application->add(new CompletionCommand());

$application->run();
