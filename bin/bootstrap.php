<?php

/**
 * This script runs ADS. It does the following:
 *   - Includes the Composer autoload file
 *   - Starts a container with the input, output, application, and configuration objects
 *   - Starts a runner instance and runs the command
 *   - Exits with a status code
 */

$pharPath = Phar::running(true);
if ($pharPath) {
    include_once "$pharPath/vendor/autoload.php";
} else {
    $repo_root = __DIR__ . '/..';

    $possible_autoloader_locations = [
      $repo_root . '/../../autoload.php',
      $repo_root . '/vendor/autoload.php',

    ];

    foreach ($possible_autoloader_locations as $location) {
        if (file_exists($location)) {
            $autoloader = require_once $location;
            break;
        }
    }

    if (empty($autoloader)) {
        echo 'Unable to autoload classes for ADS.' . PHP_EOL;
        exit(1);
    }
}

/**
 * Finds the root directory for the repository.
 *
 * @return null|string
 *   Root.
 */
function find_repo_root()
{
    $possible_repo_roots = [
      getcwd(),
      dirname(__DIR__) . '/',
      dirname(dirname(dirname(__DIR__))) . '/',
    ];
    // Check for PWD - some local environments will not have this key.
    if (isset($_SERVER['PWD'])) {
        array_unshift($possible_repo_roots, $_SERVER['PWD']);
    }
    foreach ($possible_repo_roots as $possible_repo_root) {
        if ($repo_root = find_directory_containing_files($possible_repo_root, ['docroot', 'vendor/autoload.php'])) {
            return realpath($repo_root);
        }
    }

    return null;
}

/**
 * Traverses file system upwards in search of a given file.
 *
 * Begins searching for $file in $working_directory and climbs up directories
 * $max_height times, repeating search.
 *
 * @param string $working_directory
 *   Working directory.
 * @param array $files
 *   Files.
 * @param int $max_height
 *   Max Height.
 *
 * @return bool|string
 *   FALSE if file was not found. Otherwise, the directory path containing the
 *   file.
 */
function find_directory_containing_files($working_directory, array $files, $max_height = 10)
{
    // Find the root directory of the git repository containing BLT.
    // We traverse the file tree upwards $max_height times until we find
    // vendor/bin/blt.
    $file_path = $working_directory;
    for ($i = 0; $i <= $max_height; $i++) {
        if (files_exist($file_path, $files)) {
            return $file_path;
        } else {
            $file_path = dirname($file_path) . '';
        }
    }

    return false;
}

/**
 * Determines if an array of files exist in a particular directory.
 *
 * @param string $dir
 *   Dir.
 * @param array $files
 *   Files.
 *
 * @return bool
 *   Exists.
 */
function files_exist($dir, array $files)
{
    foreach ($files as $file) {
        if (!file_exists($dir . '/' . $file)) {
            return false;
        }
    }
    return true;
}
