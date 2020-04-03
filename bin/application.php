#!/usr/bin/env php
<?php

/**
 * This script runs ADS. It does the following:
 *   - Includes the Composer autoload file
 *   - Starts a container with the input, output, application, and configuration objects
 *   - Starts a runner instance and runs the command
 *   - Exits with a status code
 */

$pharPath = \Phar::running(true);
if ($pharPath) {
    include_once("$pharPath/vendor/autoload.php");
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
        echo 'Unable to autoload classes for yml-cli.' . PHP_EOL;
        exit(1);
    }
}

use Acquia\Ads\Ads;
use Acquia\Ads\Command\ApiCommand;
use Acquia\Ads\Command\AuthCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

// Create the input and output objects for ads to run against.
$input = new ArgvInput($_SERVER['argv']);
$output = new ConsoleOutput();

/**
 * Running ads.
 */
$application = new Ads('ads', '@package_version@');
$application->addCommands([
    new AuthCommand(),
    new ApiCommand(),
]);

$status_code = $application->run($input, $output);
exit($status_code);
