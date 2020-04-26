<?php

use Acquia\Ads\AdsApplication;
use Acquia\Ads\Command\Api\ApiListCommand;
use Acquia\Ads\Command\AuthCommand;
use Acquia\Ads\Command\Ide\IdeCreateCommand;
use Acquia\Ads\Command\Ide\IdeDeleteCommand;
use Acquia\Ads\Command\Ide\IdeListCommand;
use Acquia\Ads\Command\Ide\IdeOpenCommand;
use Acquia\Ads\Command\LinkCommand;
use Acquia\Ads\Command\Logs\LogsTailCommand;
use Acquia\Ads\Command\Logs\LogsTailDbCommand;
use Acquia\Ads\Command\NewCommand;
use Acquia\Ads\Command\RefreshCommand;
use Acquia\Ads\Command\Remote\AliasesDownloadCommand;
use Acquia\Ads\Command\Remote\AliasListCommand;
use Acquia\Ads\Command\Remote\DrushCommand;
use Acquia\Ads\Command\Remote\SshCommand;
use Acquia\Ads\Command\Ssh\SshKeyCreateCommand;
use Acquia\Ads\Command\Ssh\SshKeyCreateUploadCommand;
use Acquia\Ads\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Ads\Command\Ssh\SshKeyListCommand;
use Acquia\Ads\Command\Ssh\SshKeyUploadCommand;
use Acquia\Ads\Command\TelemetryCommand;
use Acquia\Ads\Command\UnlinkCommand;
use Acquia\Ads\Command\UpdateCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

$pharPath = Phar::running(true);
if ($pharPath) {
    require_once "$pharPath/vendor/autoload.php";
} else {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Create the input and output objects for ads to run against.
$input = new ArgvInput($_SERVER['argv']);
$output = new ConsoleOutput();
$logger = new ConsoleLogger($output);
$repo_root = find_repo_root();

/**
 * Running ads.
 */
$application = new AdsApplication('ads', '@package_version@', $input, $output, $logger, $repo_root);
$application->addCommands([
  new AliasesDownloadCommand(),
  new AliasListCommand(),
  new AuthCommand(),
  new ApiListCommand(),
  new DrushCommand(),
  new IdeCreateCommand(),
  new IdeDeleteCommand(),
  new IdeListCommand(),
  new IdeOpenCommand(),
  new LinkCommand(),
  new LogsTailCommand(),
  new LogsTailDbCommand(),
  new NewCommand(),
  new RefreshCommand(),
  new SshCommand(),
  new SshKeyCreateCommand(),
  new SshKeyDeleteCommand(),
  new SshKeyListCommand(),
  new SshKeyUploadCommand(),
  new SshKeyCreateUploadCommand(),
  new TelemetryCommand(),
  new UnlinkCommand(),
  new UpdateCommand(),
]);

$status_code = $application->run($input, $output);
exit($status_code);

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
    ];
    // Check for PWD - some local environments will not have this key.
    if (isset($_SERVER['PWD']) && !in_array($_SERVER['PWD'], $possible_repo_roots, true)) {
        array_unshift($possible_repo_roots, $_SERVER['PWD']);
    }
    foreach ($possible_repo_roots as $possible_repo_root) {
        if ($repo_root = find_directory_containing_files($possible_repo_root, ['docroot/index.php'])) {
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
        }

        $file_path = dirname($file_path) . '';
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
        if (file_exists($dir . '/' . $file)) {
            return true;
        }
    }

    return false;
}
