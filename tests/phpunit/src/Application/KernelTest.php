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
Acquia CLI

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
  docs                     Open Acquia product documentation in a web browser
  help                     Display help for a command
  list                     [self:list] List commands
 acsf
  acsf:list                [acsf] List all Acquia Cloud Site Factory commands
 api
  api:list                 [api] List all API commands
 app
  app:link                 [link] Associate your project with a Cloud Platform application
  app:log:tail             [tail|log:tail] Tail the logs from your environments
  app:new:from:drupal7     [from:d7|ama] Generate a new Drupal 9+ project from a Drupal 7 application using the default Acquia Migrate Accelerate recommendations.
  app:new:local            [new] Create a new Drupal or Next.js project
EOD;
    }

    private function getEnd(): string
    {
        return <<<EOD
  app:task-wait            Wait for a task to complete
  app:unlink               [unlink] Remove local association between your project and a Cloud Platform application
  app:vcs:info             Get all branches and tags of the application with the deployment status
 archive
  archive:export           Export an archive of the Drupal application including code, files, and database
 auth
  auth:acsf-login          Register Site Factory API credentials
  auth:acsf-logout         Remove Site Factory API credentials
  auth:login               [login] Register Cloud Platform API credentials
  auth:logout              [logout] Remove Cloud Platform API credentials
 codestudio
  codestudio:php-version   Change the PHP version in Code Studio
  codestudio:wizard        [cs:wizard] Create and/or configure a new Code Studio project for a given Cloud Platform application
 email
  email:configure          [ec] Configure Platform email for one or more applications
  email:info               Print information related to Platform Email set up in a subscription.
 env
  env:certificate-create   Install an SSL certificate.
  env:create               Create a new Continuous Delivery Environment (CDE)
  env:cron-copy            Copy all cron tasks from one Cloud Platform environment to another
  env:delete               Delete a Continuous Delivery Environment (CDE)
  env:mirror               Makes one environment identical to another in terms of code, database, files, and configuration.
 ide
  ide:create               Create a Cloud IDE
  ide:delete               Delete a Cloud IDE
  ide:info                 Print information about a Cloud IDE
  ide:list:app             [ide:list] List available Cloud IDEs belonging to a given application
  ide:list:mine            List Cloud IDEs belonging to you
  ide:open                 Open a Cloud IDE in your browser
 pull
  pull:all                 [refresh|pull] Copy code, database, and files from a Cloud Platform environment
  pull:code                Copy code from a Cloud Platform environment
  pull:database            [pull:db] Import database backup from a Cloud Platform environment
  pull:files               Copy Drupal public files from a Cloud Platform environment to your local environment
  pull:run-scripts         Execute post pull scripts
 push
  push:artifact            Build and push a code artifact to a Cloud Platform environment
  push:database            [push:db] Push a database from your local environment to a Cloud Platform environment
  push:files               Copy Drupal public files from your local environment to a Cloud Platform environment
 remote
  remote:aliases:download  Download Drush aliases for the Cloud Platform
  remote:aliases:list      [aliases|sa] List all aliases for the Cloud Platform environments
  remote:drush             [drush|dr] Run a Drush command remotely on a Cloud Platform environment
  remote:ssh               [ssh] Use SSH to open a shell or run a command in a Cloud Platform environment
 self
  self:clear-caches        [cc|cr] Clears local Acquia CLI caches
  self:telemetry:disable   [telemetry:disable] Disable anonymous sharing of usage and performance data
  self:telemetry:enable    [telemetry:enable] Enable anonymous sharing of usage and performance data
  self:telemetry:toggle    [telemetry] Toggle anonymous sharing of usage and performance data
 ssh-key
  ssh-key:create           Create an SSH key on your local machine
  ssh-key:create-upload    Create an SSH key on your local machine and upload it to the Cloud Platform
  ssh-key:delete           Delete an SSH key
  ssh-key:info             Print information about an SSH key
  ssh-key:list             List your local and remote SSH keys
  ssh-key:upload           Upload a local SSH key to the Cloud Platform

EOD;
    }
}
