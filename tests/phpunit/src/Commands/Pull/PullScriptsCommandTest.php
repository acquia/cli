<?php

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\Pull\PullFilesCommand;
use Acquia\Cli\Command\Pull\PullScriptsCommand;
use Acquia\Cli\Helpers\SshHelper;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class PullScriptsCommandTest.
 *
 * @property \Acquia\Cli\Command\Pull\PullScriptsCommand $command
 * @package Acquia\Cli\Tests\Commands\Pull
 */
class PullScriptsCommandTest extends PullCommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(PullScriptsCommand::class);
  }

  public function testRefreshScripts(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();

    $this->command->localMachineHelper = $local_machine_helper->reveal();

    // Composer.
    $this->mockExecuteComposerExists($local_machine_helper);
    $this->mockExecuteComposerInstall($local_machine_helper, $process);

    // Drush.
    $drush_connection_exists = TRUE;
    $this->mockExecuteDrushExists($local_machine_helper);
    $this->mockExecuteDrushStatus($local_machine_helper, $drush_connection_exists);
    if ($drush_connection_exists) {
      $this->mockExecuteDrushCacheRebuild($local_machine_helper, $process);
      $this->mockExecuteDrushSqlSanitize($local_machine_helper, $process);
    }

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];

    $this->executeCommand([
      'dir' => $this->projectFixtureDir,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
  }

}
