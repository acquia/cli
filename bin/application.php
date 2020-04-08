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

use Acquia\Ads\AdsApplication;
use Acquia\Ads\Command\Api\ApiListCommand;
use Acquia\Ads\Command\AuthCommand;
use Acquia\Ads\Command\CloneCommand;
use Acquia\Ads\Command\Ide\IdeCreateCommand;
use Acquia\Ads\Command\Ide\IdeDeleteCommand;
use Acquia\Ads\Command\Ide\IdeListCommand;
use Acquia\Ads\Command\Ide\IdeOpenCommand;
use Acquia\Ads\Command\LinkCommand;
use Acquia\Ads\Command\ListCommand;
use Acquia\Ads\Command\NewCommand;
use Acquia\Ads\Command\RefreshCommand;
use Acquia\Ads\Command\SiteAliasesCommand;
use Acquia\Ads\Command\SshKeyCommand;
use Acquia\Ads\Command\UpdateCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

// Create the input and output objects for ads to run against.
$input = new ArgvInput($_SERVER['argv']);
$output = new ConsoleOutput();
$logger = new ConsoleLogger($output);

/**
 * Running ads.
 */
$application = new AdsApplication('ads', '@package_version@', $logger);
$application->addCommands([
    new AuthCommand(),
    new ApiListCommand(),
    new CloneCommand(),
    new IdeCreateCommand(),
    new IdeDeleteCommand(),
    new IdeListCommand(),
    new IdeOpenCommand(),
    new LinkCommand(),
    new ListCommand(),
    new NewCommand(),
    new RefreshCommand(),
    new SiteAliasesCommand(),
    new SshKeyCommand(),
    new UpdateCommand(),
]);

$status_code = $application->run($input, $output);
exit($status_code);
