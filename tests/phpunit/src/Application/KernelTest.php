<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Tests\ApplicationTestBase;

class KernelTest extends ApplicationTestBase
{
    /**
     * @group serial
     */
    public function testRun(): void
    {
        $this->setInput([
            'command' => 'list',
        ]);
        $buffer = $this->runApp();
        // A bit dumb that we need to break these up, but the available commands vary based on whether a browser is available or the session is interactive.
        // Could probably handle that more intelligently...
        $this->assertStringStartsWith($this->getStart(), $buffer);
        $this->assertStringEndsWith($this->getEnd(), $buffer);
    }

    private function getStart(): string
    {
        return <<<EOD
Acquia CLI dev-unknown

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display help for the given command. When no command is given display help for the list command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  completion               Dump the shell completion script
  docs                     Open Acquia product documentation in a web browser (Added in 1.18.0).
  help                     Display help for a command
  list                     [self:list] List commands
 acsf
  acsf:list                [acsf] List all Acquia Cloud Site Factory commands (Added in 1.30.1).
 api
  api:list                 [api] List all API commands (Added in 1.0.0).
 app
  app:link                 [link] Associate your project with a Cloud Platform application (Added in 1.23.1).
  app:log:tail             [tail|log:tail] Tail the logs from your environments (Added in 1.23.1).
  app:new:from:drupal7     [from:d7|ama] Generate a new Drupal 9+ project from a Drupal 7 application using the default Acquia Migrate Accelerate recommendations. (Added in 2.14.0).
  app:new:local            [new] Create a new Drupal or Next.js project (Added in 2.0.0).
EOD;
    }

    private function getEnd(): string
    {
        return <<<EOD
  app:task-wait            Wait for a task to complete (Added in 2.0.0).
  app:unlink               [unlink] Remove local association between your project and a Cloud Platform application (Added in 2.0.0).
  app:vcs:info             Get all branches and tags of the application with the deployment status (Added in 2.8.0).
 archive
  archive:export           Export an archive of the Drupal application including code, files, and database (Added in 1.12.0).
 auth
  auth:acsf-login          Register Site Factory API credentials (Added in 2.20.1).
  auth:acsf-logout         Remove Site Factory API credentials (Added in 2.20.1).
  auth:login               [login] Register Cloud Platform API credentials (Added in 1.1.0).
  auth:logout              [logout] Remove Cloud Platform API credentials (Added in 1.1.0).
 codestudio
  codestudio:php-version   Change the PHP version in Code Studio (Added in 2.7.0).
  codestudio:wizard        [cs:wizard] Create and/or configure a new Code Studio project for a given Cloud Platform application (Added in 1.21.0).
 env
  env:certificate-create   Install an SSL certificate. (Added in 2.10.0).
  env:create               Create a new Continuous Delivery Environment (CDE) (Added in 2.0.0).
  env:cron-copy            Copy all cron tasks from one Cloud Platform environment to another (Added in 2.0.0).
  env:delete               Delete a Continuous Delivery Environment (CDE) (Added in 2.0.0).
  env:mirror               Makes one environment identical to another in terms of code, database, files, and configuration. (Added in 2.0.0).
 ide
  ide:create               Create a Cloud IDE (Added in 1.0.0).
  ide:delete               Delete a Cloud IDE (Added in 1.0.0).
  ide:info                 Print information about a Cloud IDE (Added in 1.2.0).
  ide:list:app             [ide:list] List available Cloud IDEs belonging to a given application (Added in 1.0.0).
  ide:list:mine            List Cloud IDEs belonging to you (Added in 1.18.0).
  ide:open                 Open a Cloud IDE in your browser (Added in 1.0.0).
 pull
  pull:all                 [refresh|pull] Copy code, database, and files from a Cloud Platform environment (Added in 1.1.0).
  pull:code                Copy code from a Cloud Platform environment (Added in 1.1.0).
  pull:database            [pull:db] Import database backup from a Cloud Platform environment (Added in 1.1.0).
  pull:files               Copy Drupal public files from a Cloud Platform environment to your local environment (Added in 1.1.0).
  pull:run-scripts         Execute post pull scripts (Added in 1.1.0).
 push
  push:artifact            Build and push a code artifact to a Cloud Platform environment (Added in 1.11.0).
  push:database            [push:db] Push a database from your local environment to a Cloud Platform environment (Added in 1.1.0).
  push:files               Copy Drupal public files from your local environment to a Cloud Platform environment (Added in 1.1.0).
 remote
  remote:aliases:download  Download Drush aliases for the Cloud Platform (Added in 1.0.0).
  remote:aliases:list      [aliases|sa] List all aliases for the Cloud Platform environments (Added in 1.0.0).
  remote:drush             [drush|dr] Run a Drush command remotely on a Cloud Platform environment (Added in 1.0.0).
  remote:ssh               [ssh] Use SSH to open a shell or run a command in a Cloud Platform environment (Added in 1.0.0).
 self
  self:clear-caches        [cc|cr] Clears local Acquia CLI caches (Added in 2.0.0).
  self:info                Print information about the running version of Acquia CLI (Added in 2.31.0).
  self:telemetry:disable   [telemetry:disable] Disable anonymous sharing of usage and performance data (Added in 2.0.0).
  self:telemetry:enable    [telemetry:enable] Enable anonymous sharing of usage and performance data (Added in 2.0.0).
  self:telemetry:toggle    [telemetry] Toggle anonymous sharing of usage and performance data (Added in 2.0.0).
 ssh-key
  ssh-key:create           Create an SSH key on your local machine (Added in 1.0.0).
  ssh-key:create-upload    Create an SSH key on your local machine and upload it to the Cloud Platform (Added in 1.0.0).
  ssh-key:delete           Delete an SSH key (Added in 1.0.0).
  ssh-key:info             Print information about an SSH key (Added in 1.17.0).
  ssh-key:list             List your local and remote SSH keys (Added in 1.0.0).
  ssh-key:upload           Upload a local SSH key to the Cloud Platform (Added in 1.0.0).

EOD;
    }
}
