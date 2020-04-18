<?php

use Acquia\Ads\AdsApplication;
use Acquia\Ads\Command\Api\ApiListCommand;
use Acquia\Ads\Command\AuthCommand;
use Acquia\Ads\Command\CloneCommand;
use Acquia\Ads\Command\Ide\IdeCreateCommand;
use Acquia\Ads\Command\Ide\IdeDeleteCommand;
use Acquia\Ads\Command\Ide\IdeListCommand;
use Acquia\Ads\Command\Ide\IdeOpenCommand;
use Acquia\Ads\Command\LinkCommand;
use Acquia\Ads\Command\NewCommand;
use Acquia\Ads\Command\RefreshCommand;
use Acquia\Ads\Command\Remote\AliasesDownloadCommand;
use Acquia\Ads\Command\Remote\AliasListCommand;
use Acquia\Ads\Command\Remote\DrushCommand;
use Acquia\Ads\Command\Remote\SshCommand;
use Acquia\Ads\Command\SiteAliasesCommand;
use Acquia\Ads\Command\Ssh\SshKeyCreateCommand;
use Acquia\Ads\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Ads\Command\Ssh\SshKeyListCommand;
use Acquia\Ads\Command\Ssh\SshKeyUploadCommand;
use Acquia\Ads\Command\UpdateCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

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
    new NewCommand(),
    new RefreshCommand(),
    new SshCommand(),
    new SshKeyCreateCommand(),
    new SshKeyDeleteCommand(),
    new SshKeyListCommand(),
    new SshKeyUploadCommand(),
    new UpdateCommand(),
]);

$status_code = $application->run($input, $output);
exit($status_code);
