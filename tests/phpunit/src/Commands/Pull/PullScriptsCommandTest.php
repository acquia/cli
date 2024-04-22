<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullScriptsCommand;
use Symfony\Component\Filesystem\Path;

/**
 * @property \Acquia\Cli\Command\Pull\PullScriptsCommand $command
 */
class PullScriptsCommandTest extends PullCommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(PullScriptsCommand::class);
  }

  public function testRefreshScripts(): void {
    touch(Path::join($this->projectDir, 'composer.json'));
    $localMachineHelper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();

    // Composer.
    $this->mockExecuteComposerExists($localMachineHelper);
    $this->mockExecuteComposerInstall($localMachineHelper, $process);

    // Drush.
    $this->mockExecuteDrushExists($localMachineHelper);
    $this->mockExecuteDrushStatus($localMachineHelper, $this->projectDir);
    $this->mockExecuteDrushCacheRebuild($localMachineHelper, $process);
    $this->mockExecuteDrushSqlSanitize($localMachineHelper, $process);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Choose an Acquia environment:
      0,
    ];

    $this->executeCommand([
      '--dir' => $this->projectDir,
    ], $inputs);

    $this->getDisplay();
  }

}
